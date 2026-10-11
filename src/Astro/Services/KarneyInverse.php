<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Ellipsoidal geodesics on WGS84 (Karney inverse, vendored).
 *
 * Vendored subset of GeographicLib 2.1 (``geodesic.py`` / ``geomath.py`` /
 * ``geodesiccapability.py``) by Charles F. F. Karney, MIT/X11 licensed::
 *
 *     Copyright (c) Charles Karney (2011-2022) <karney@alum.mit.edu>
 *
 * Algorithms derived in C. F. F. Karney, "Algorithms for geodesics",
 * *J. Geodesy* 87, 43-55 (2013), https://doi.org/10.1007/s00190-012-0578-z.
 *
 * Vincenty (1975) is deliberately omitted: its iteration is documented
 * non-convergent for nearly-antipodal points, while Karney's hybrid
 * Newton/astroid iteration converges for all inputs.
 *
 * Transcription of a vendored GeographicLib 2.1 subset pinned at commit
 * ``a7baa8b`` of the alfalak Python pin (``src/alfalak/astronomy/Geodesy.py``),
 * helpers transcribed verbatim. Kept: the inverse-problem path only
 * (series helpers A1/C1/A2/C2/A3/C3, ``_lengths``, ``_inverse_start``,
 * ``_lambda12``, ``_gen_inverse``). Omitted: direct/line/polygon/area
 * machinery (C1', C4, geodesic tables) and the output-mask bits the inverse
 * path never requests.
 *
 * Python -> PHP float machinery (1:1, no re-derivation):
 * - ``sys.float_info.mant_dig`` = 53 -> ``_MAXIT2 = 20 + 53 + 10``.
 * - ``sys.float_info.epsilon`` = 2^-52 -> ``PHP_FLOAT_EPSILON``.
 * - ``sys.float_info.min`` = 2.2250738585072014e-308 -> ``PHP_FLOAT_MIN``
 *   (``sqrt`` of it is the same IEEE 754 double as in CPython).
 * - ``math.radians``/``degrees``/``sqrt``/``sin``/``cos``/``tan``/``asin``/
 *   ``acos``/``atan``/``atan2``/``sinh``/``cosh``/``tanh``/``fmod``/``hypot``
 *   map 1:1 to the PHP spellings of the same names; ``math.isfinite`` ->
 *   ``is_finite``, ``math.isnan`` -> ``is_nan``, ``math.remainder`` ->
 *   ``remainder()`` below (NOT ``fmod``: the quotient is rounded to nearest
 *   with ties to even) and ``math.copysign`` -> ``copysign()`` below (PHP has
 *   no built-in).
 * - Python 2-tuples -> ``array{0: float, 1: float}``; 10-tuples -> indexed
 *   arrays; series/scratch tables -> ``array<int, float>``. The pin's
 *   mutable ``c: list[float]`` out-parameters (``_c1f``/``_c2f``/``_c3f``)
 *   become arrays that the helper returns filled.
 * - Python ``int`` index arithmetic stays ``int``; ``//`` -> ``intdiv``.
 * - ``_A3X``/``_C3X`` are built once per solve (the pin builds them at import
 *   time); PHP forbids function calls in constant expressions.
 *
 * @internal Strategies own the models; this owns the raw math.
 */
final class KarneyInverse
{
    /**
     * WGS84 equatorial radius in metres (pin Geodesy.py:35, defining, exact).
     */
    public const SEMI_MAJOR_M = 6378137.0;

    /**
     * WGS84 flattening (pin Geodesy.py:38, defining, exact).
     */
    public const FLATTENING = 1.0 / 298.257223563;

    /**
     * 1 - f (pin _F1, Geodesy.py:47).
     */
    private const F1 = 1.0 - self::FLATTENING;

    /**
     * First eccentricity squared, f * (2 - f) (pin _E2, Geodesy.py:48).
     */
    private const E2 = self::FLATTENING * (2.0 - self::FLATTENING);

    /**
     * Second eccentricity squared (pin _EP2, Geodesy.py:49).
     */
    private const EP2 = self::E2 / (self::F1 * self::F1);

    /**
     * Third flattening n = f / (2 - f) (pin _N, Geodesy.py:50).
     */
    private const N = self::FLATTENING / (2.0 - self::FLATTENING);

    /**
     * Polar semi-axis a * (1 - f) (pin _B / WGS84_B, Geodesy.py:41).
     */
    private const B = self::SEMI_MAJOR_M * self::F1;

    /**
     * Series order for A1 (pin _N_A1, Geodesy.py:54).
     */
    private const N_A1 = 6;

    /**
     * Series order for C1 (pin _N_C1, Geodesy.py:55).
     */
    private const N_C1 = 6;

    /**
     * Series order for A2 (pin _N_A2, Geodesy.py:56).
     */
    private const N_A2 = 6;

    /**
     * Series order for C2 (pin _N_C2, Geodesy.py:57).
     */
    private const N_C2 = 6;

    /**
     * Series order for A3 (pin _N_A3, Geodesy.py:58).
     */
    private const N_A3 = 6;

    /**
     * Extended A3 order (pin _N_A3X, Geodesy.py:59).
     */
    private const N_A3X = 6;

    /**
     * Series order for C3 (pin _N_C3, Geodesy.py:60).
     */
    private const N_C3 = 6;

    /**
     * Extended C3 order (pin _N_C3X, Geodesy.py:61).
     */
    private const N_C3X = 15;

    /**
     * First Newton iteration cap (pin _MAXIT1, Geodesy.py:63).
     */
    private const MAXIT1 = 20;

    /**
     * Overall iteration cap: 20 + mant_dig(53) + 10 = 83 (pin _MAXIT2).
     */
    private const MAXIT2 = self::MAXIT1 + 53 + 10;

    /**
     * Convergence tolerance 2^-52 (pin _TOL0, Geodesy.py:67).
     */
    private const TOL0 = PHP_FLOAT_EPSILON;

    /**
     * Latitude tolerance 200 * _TOL0 (pin _TOL1, Geodesy.py:68).
     */
    private const TOL1 = 200.0 * self::TOL0;

    /**
     * Bisection tolerance (pin _TOLB, Geodesy.py:70).
     */
    private const TOLB = self::TOL0;

    /**
     * sqrt(sys.float_info.min): pin _TINY (Geodesy.py:66). Same IEEE 754
     * double as CPython's math.sqrt of the same input.
     *
     * @return float sqrt of the smallest normal double.
     */
    private static function tiny(): float
    {
        return sqrt(PHP_FLOAT_MIN);
    }

    /**
     * sqrt(sys.float_info.epsilon) = sqrt(2^-52) = 2^-26 (pin _TOL2).
     *
     * @return float sqrt of Python's float epsilon.
     */
    private static function tol2(): float
    {
        return sqrt(PHP_FLOAT_EPSILON);
    }

    /**
     * Astroid-latitude threshold 1000 * _TOL2 (pin _XTHRESH).
     *
     * @return float Threshold for the astroid branch.
     */
    private static function xthresh(): float
    {
        return 1000.0 * self::tol2();
    }

    /**
     * Short-line epsilon tolerance (pin _ETOL2, Geodesy.py:72-74).
     *
     * @return float Auxiliary-sphere short-line tolerance.
     */
    private static function etol2(): float
    {
        return 0.1 * self::tol2()
            / sqrt(max(0.001, abs(self::FLATTENING)) * min(1.0, 1.0 - self::FLATTENING / 2.0) / 2.0);
    }

    /**
     * Solve the inverse geodesic problem on the WGS84 ellipsoid (Karney 2013).
     *
     * Pin Geodesy.py:881-925 (geodesic_inverse). Range/finiteness checks are
     * transcribed verbatim for both points; the pin's `bool`/`Decimal`/
     * `numbers.Real` type branches are omitted because the PHP `float`
     * parameter type makes them unreachable (a non-float argument is a
     * TypeError before this body runs).
     *
     * @param float $lat1 Latitude of the first point in degrees, within [-90, 90].
     * @param float $lon1 Longitude of the first point in degrees, within [-180, 180].
     * @param float $lat2 Latitude of the second point in degrees, within [-90, 90].
     * @param float $lon2 Longitude of the second point in degrees, within [-180, 180].
     * @return array{direction: float, distanceKm: float} Forward azimuth in degrees,
     *         unwound to (-180, 180] (may be negative; callers unwind), plus the
     *         geodesic distance in kilometres. Never NaN/INF.
     */
    public function inverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        foreach (['lat1' => $lat1, 'lat2' => $lat2] as $name => $value) {
            if (! is_finite($value) || ! (-90.0 <= $value && $value <= 90.0)) {
                throw new ValidationException(sprintf(
                    'Latitude must be within [-90.0, 90.0], got %s (%s).',
                    $this->floatRepr($value),
                    $name,
                ));
            }
        }

        foreach (['lon1' => $lon1, 'lon2' => $lon2] as $name => $value) {
            if (! is_finite($value) || ! (-180.0 <= $value && $value <= 180.0)) {
                throw new ValidationException(sprintf(
                    'Longitude must be within [-180.0, 180.0], got %s (%s).',
                    $this->floatRepr($value),
                    $name,
                ));
            }
        }

        // Only four of _gen_inverse's ten outputs are read by this path:
        // the azimuth comes from salp1/calp1 (sig12 is not the azimuth), and
        // s12 is the distance. Positions 0 (a12) and 4-9 (salp2, calp2, m12,
        // m12_out, m21, s_area) are never consumed.
        $result = $this->genInverse($lat1, $lon1, $lat2, $lon2);
        $s12 = $result[1];
        $salp1 = $result[2];
        $calp1 = $result[3];

        $azi = $this->atan2d($salp1, $calp1);

        // Pin Geodesy.py:921-924: exact -180 maps to 180, and the `+ 0.0`
        // carries over so a -0.0 azimuth (e.g. fmod(-0.0, 360) downstream)
        // can never leak out of this engine.
        if ($azi === -180.0) {
            $azi = 180.0;
        } else {
            $azi = $azi + 0.0;
        }

        if (! is_finite($azi) || ! is_finite($s12)) {
            throw new AstronomicalException(
                'KarneyInverse produced a non-finite result.',
            );
        }

        return ['direction' => $azi, 'distanceKm' => $s12 / 1000.0];
    }

    /**
     * Python repr of a float, for validation messages (pin f-string {value!r}).
     *
     * @param float $value Value to render.
     * @return string Value rendered the way Python's repr would.
     */
    private function floatRepr(float $value): string
    {
        if (is_nan($value)) {
            return 'nan';
        }

        if ($value === INF) {
            return 'inf';
        }

        if ($value === -INF) {
            return '-inf';
        }

        return var_export($value, true);
    }

    /**
     * Pin Geodesy.py:651-878 (_gen_inverse), with the outmask simplified.
     *
     * The pin always runs _gen_inverse with _OUTMASK = _MASK_AZIMUTH |
     * _MASK_DISTANCE and discards the rest, so the outmask bit machinery is
     * gone: _lengths is reached with three fixed combinations only —
     * (distance, reduced) from the meridian branch, (distance,) from the
     * final iterative call, and (reduced,) from the _lambda12 derivative.
     * The ten return slots are kept for shape parity with the pin even
     * though this file's only caller reads four of them.
     *
     * @param float $lat1 First latitude, validated degrees.
     * @param float $lon1 First longitude, validated degrees.
     * @param float $lat2 Second latitude, validated degrees.
     * @param float $lon2 Second longitude, validated degrees.
     * @return list<float> (a12, s12, salp1, calp1, salp2, calp2, m12, m12_out, m21, s_area).
     */
    private function genInverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $a12 = NAN;
        $s12x = NAN;
        $m12x = NAN;
        $salp1 = NAN;
        $calp1 = NAN;
        $salp2 = NAN;
        $calp2 = NAN;
        $eps = NAN;
        $ssig1 = NAN;
        $csig1 = NAN;
        $ssig2 = NAN;
        $csig2 = NAN;
        $orig = [$lat1, $lon1, $lat2, $lon2];

        [$lon12, $lon12s] = $this->angDiff($lon1, $lon2);
        $lonsign = $this->copysign(1.0, $lon12);
        $lon12 = $lonsign * $lon12;
        $lon12s = $lonsign * $lon12s;
        $lam12 = deg2rad($lon12);
        [$slam12, $clam12] = $this->sincosde($lon12, $lon12s);
        $lon12s = (180.0 - $lon12) - $lon12s;

        $lat1 = $this->angRound(abs($lat1) <= 90.0 ? $lat1 : NAN);
        $lat2 = $this->angRound(abs($lat2) <= 90.0 ? $lat2 : NAN);
        $swapp = (abs($lat1) < abs($lat2) || is_nan($lat2)) ? -1 : 1;

        if ($swapp < 0) {
            $lonsign *= -1.0;
            [$lat2, $lat1] = [$lat1, $lat2];
        }

        $latsign = $this->copysign(1.0, -$lat1);
        $lat1 *= $latsign;
        $lat2 *= $latsign;

        [$sbet1, $cbet1] = $this->sincosd($lat1);
        $sbet1 *= self::F1;
        [$sbet1, $cbet1] = $this->norm($sbet1, $cbet1);
        $cbet1 = max(self::tiny(), $cbet1);

        [$sbet2, $cbet2] = $this->sincosd($lat2);
        $sbet2 *= self::F1;
        [$sbet2, $cbet2] = $this->norm($sbet2, $cbet2);
        $cbet2 = max(self::tiny(), $cbet2);

        if ($cbet1 < -$sbet1) {
            if ($cbet2 === $cbet1) {
                $sbet2 = $this->copysign($sbet1, $sbet2);
            }
        } else {
            if (abs($sbet2) === -$sbet1) {
                $cbet2 = $cbet1;
            }
        }

        $dn1 = sqrt(1.0 + self::EP2 * self::sq($sbet1));
        $dn2 = sqrt(1.0 + self::EP2 * self::sq($sbet2));

        $c1a = array_fill(0, self::N_C1 + 1, 0.0);
        $c2a = array_fill(0, self::N_C2 + 1, 0.0);
        $c3a = array_fill(0, self::N_C3, 0.0);
        $a3x = $this->a3Coeff();
        $c3x = $this->c3Coeff();

        $meridian = $lat1 === -90.0 || $slam12 === 0.0;

        if ($meridian) {
            $calp1 = $clam12;
            $salp1 = $slam12;
            $calp2 = 1.0;
            $salp2 = 0.0;

            $ssig1 = $sbet1;
            $csig1 = $calp1 * $cbet1;
            $ssig2 = $sbet2;
            $csig2 = $calp2 * $cbet2;

            $sig12 = atan2(
                max(0.0, $csig1 * $ssig2 - $ssig1 * $csig2) + 0.0,
                $csig1 * $csig2 + $ssig1 * $ssig2,
            );

            // Pin: outmask | _MASK_DISTANCE | _MASK_REDUCEDLENGTH.
            $lengths = $this->lengths(
                self::N,
                $sig12,
                $ssig1,
                $csig1,
                $dn1,
                $ssig2,
                $csig2,
                $dn2,
                $cbet1,
                $cbet2,
                true,
                true,
                $c1a,
                $c2a,
            );
            $s12x = $lengths[0];
            $m12x = $lengths[1];

            if ($sig12 < self::tol2() || $m12x >= 0.0) {
                if ($sig12 < 3.0 * self::tiny()
                    || ($sig12 < self::TOL0 && ($s12x < 0.0 || $m12x < 0.0))) {
                    $sig12 = $m12x = $s12x = 0.0;
                }

                $m12x *= self::B;
                $s12x *= self::B;
                $a12 = rad2deg($sig12);
            } else {
                $meridian = false;
            }
        }

        // The pin's ``WGS84_F <= 0`` prolate-sphere guard is dropped: the
        // flattening is a positive constant here, so PHPStan proves it dead
        // (it is a test seam for an oblate WGS84, not a runtime branch).
        if (! $meridian && $sbet1 === 0.0 && $lon12s >= self::FLATTENING * 180.0) {
            $calp1 = $calp2 = 0.0;
            $salp1 = $salp2 = 1.0;
            $s12x = self::SEMI_MAJOR_M * $lam12;
            $sig12 = $lam12 / self::F1;
            $m12x = self::B * sin($sig12);
            $a12 = $lon12 / self::F1;
        } elseif (! $meridian) {
            [$sig12, $salp1, $calp1, $salp2, $calp2, $dnm] = $this->inverseStart(
                $sbet1,
                $cbet1,
                $dn1,
                $sbet2,
                $cbet2,
                $dn2,
                $lam12,
                $slam12,
                $clam12,
                $a3x,
            );

            if ($sig12 >= 0.0) {
                $s12x = $sig12 * self::B * $dnm;
                $m12x = self::sq($dnm) * self::B * sin($sig12 / $dnm);
                $a12 = rad2deg($sig12);
            } else {
                $numit = 0;
                $tripn = false;
                $tripb = false;
                $salp1a = self::tiny();
                $calp1a = 1.0;
                $salp1b = self::tiny();
                $calp1b = -1.0;
                $converged = false;

                while (true) {
                    [$v, $salp2, $calp2, $sig12, $ssig1, $csig1, $ssig2, $csig2, $eps, $domg12, $dv]
                        = $this->lambda12(
                            $sbet1,
                            $cbet1,
                            $dn1,
                            $sbet2,
                            $cbet2,
                            $dn2,
                            $salp1,
                            $calp1,
                            $slam12,
                            $clam12,
                            $numit < self::MAXIT1,
                            $c1a,
                            $c2a,
                            $c3a,
                            $a3x,
                            $c3x,
                        );

                    if ($tripb || abs($v) < ($tripn ? 8.0 : 1.0) * self::TOL0) {
                        $converged = true;

                        break;
                    }

                    if ($numit === self::MAXIT2) {
                        break;
                    }

                    if ($v > 0.0 && ($numit > self::MAXIT1 || $calp1 / $salp1 > $calp1b / $salp1b)) {
                        $salp1b = $salp1;
                        $calp1b = $calp1;
                    } elseif ($v < 0.0 && ($numit > self::MAXIT1 || $calp1 / $salp1 < $calp1a / $salp1a)) {
                        $salp1a = $salp1;
                        $calp1a = $calp1;
                    }

                    $numit += 1;

                    if ($numit < self::MAXIT1 && $dv > 0.0) {
                        $dalp1 = -$v / $dv;

                        if (abs($dalp1) < M_PI) {
                            $sdalp1 = sin($dalp1);
                            $cdalp1 = cos($dalp1);
                            $nsalp1 = $salp1 * $cdalp1 + $calp1 * $sdalp1;

                            if ($nsalp1 > 0.0) {
                                $calp1 = $calp1 * $cdalp1 - $salp1 * $sdalp1;
                                $salp1 = $nsalp1;
                                [$salp1, $calp1] = $this->norm($salp1, $calp1);
                                $tripn = abs($v) <= 16.0 * self::TOL0;

                                continue;
                            }
                        }
                    }

                    $salp1 = ($salp1a + $salp1b) / 2.0;
                    $calp1 = ($calp1a + $calp1b) / 2.0;
                    [$salp1, $calp1] = $this->norm($salp1, $calp1);
                    $tripn = false;
                    $tripb = abs($salp1a - $salp1) + ($calp1a - $calp1) < self::TOLB
                        || abs($salp1 - $salp1b) + ($calp1 - $calp1b) < self::TOLB;
                }

                if (! $converged) {
                    throw new AstronomicalException(sprintf(
                        'Ellipsoidal inverse failed to converge for %s, %s -> %s, %s;'
                        . ' this indicates a numerical bug, since Karney converges for all inputs.',
                        $this->floatRepr($orig[0]),
                        $this->floatRepr($orig[1]),
                        $this->floatRepr($orig[2]),
                        $this->floatRepr($orig[3]),
                    ));
                }

                // Pin: lengthmask = outmask | (_MASK_DISTANCE if
                // outmask & _MASK_REDUCEDLENGTH else _MASK_EMPTY) — outmask
                // never sets the reduced-length bit, so this is the
                // distance-only mode.
                $lengths = $this->lengths(
                    $eps,
                    $sig12,
                    $ssig1,
                    $csig1,
                    $dn1,
                    $ssig2,
                    $csig2,
                    $dn2,
                    $cbet1,
                    $cbet2,
                    true,
                    false,
                    $c1a,
                    $c2a,
                );
                $s12x = $lengths[0];
                $m12x = $lengths[1] * self::B;
                $s12x *= self::B;
                $a12 = rad2deg($sig12);
            }
        }

        // Pin Geodesy.py:867: the 0.0 + normalizes a -0.0 distance.
        $s12 = 0.0 + $s12x;

        if ($swapp < 0) {
            [$salp2, $salp1] = [$salp1, $salp2];
            [$calp2, $calp1] = [$calp1, $calp2];
        }

        $salp1 *= $swapp * $lonsign;
        $calp1 *= $swapp * $latsign;
        $salp2 *= $swapp * $lonsign;
        $calp2 *= $swapp * $latsign;

        return [$a12, $s12, $salp1, $calp1, $salp2, $calp2, NAN, NAN, NAN, NAN];
    }

    /**
     * Pin Geodesy.py:423-465 (_lengths), with the outmask simplified to two
     * booleans.
     *
     * The pin's `outmask &= _MASK_OUT` keeps only the DISTANCE (bit 10) and
     * REDUCEDLENGTH (bit 12) bits for the three modes this inverse path
     * reaches, and every branch below tests only those two bits, so the two
     * booleans are an exact equivalent (the AZIMUTH bit, and the
     * GEODESICSCALE / area outputs the subset omits, never survive the mask
     * or are never tested here). Positional NAN initialisation mirrors the
     * pin leaving the unrequested outputs as NaN.
     *
     * @param float $eps Auxiliary-sphere eccentricity for this azimuth.
     * @param float $sig12 Spherical arc length.
     * @param float $ssig1 Sine of the first spherical angle.
     * @param float $csig1 Cosine of the first spherical angle.
     * @param float $dn1 First reduced-latitude denominator.
     * @param float $ssig2 Sine of the second spherical angle.
     * @param float $csig2 Cosine of the second spherical angle.
     * @param float $dn2 Second reduced-latitude denominator.
     * @param float $cbet1 Cosine of the first reduced latitude.
     * @param float $cbet2 Cosine of the second reduced latitude.
     * @param bool $distance True when the distance output is requested.
     * @param bool $reducedLength True when the reduced-length output is requested.
     * @param array<int, float> $c1a C1 series scratch, filled from eps.
     * @param array<int, float> $c2a C2 series scratch, filled from eps when reduced.
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float} (s12b, m12b, m0, m12, m21).
     */
    private function lengths(
        float $eps,
        float $sig12,
        float $ssig1,
        float $csig1,
        float $dn1,
        float $ssig2,
        float $csig2,
        float $dn2,
        float $cbet1,
        float $cbet2,
        bool $distance,
        bool $reducedLength,
        array $c1a,
        array $c2a,
    ): array {
        $s12b = NAN;
        $m12b = NAN;
        $m12 = NAN;
        $m21 = NAN;
        $a1 = NAN;
        $a2 = NAN;
        $m0x = NAN;
        $j12 = NAN;

        if ($distance || $reducedLength) {
            $a1 = $this->a1m1f($eps);
            $c1a = $this->c1f($eps, $c1a);

            if ($reducedLength) {
                $a2 = $this->a2m1f($eps);
                $c2a = $this->c2f($eps, $c2a);
                $m0x = $a1 - $a2;
                $a2 = 1.0 + $a2;
            }

            $a1 = 1.0 + $a1;
        }

        if ($distance) {
            $b1 = $this->sincosSeries($ssig2, $csig2, $c1a)
                - $this->sincosSeries($ssig1, $csig1, $c1a);
            $s12b = $a1 * ($sig12 + $b1);

            if ($reducedLength) {
                $b2 = $this->sincosSeries($ssig2, $csig2, $c2a)
                    - $this->sincosSeries($ssig1, $csig1, $c2a);
                $j12 = $m0x * $sig12 + ($a1 * $b1 - $a2 * $b2);
            }
        } elseif ($reducedLength) {
            for ($lev = 1; $lev < self::N_C2; $lev++) {
                $c2a[$lev] = $a1 * $c1a[$lev] - $a2 * $c2a[$lev];
            }

            $j12 = $m0x * $sig12 + (
                $this->sincosSeries($ssig2, $csig2, $c2a)
                - $this->sincosSeries($ssig1, $csig1, $c2a)
            );
        }

        if ($reducedLength) {
            $m12b = $dn2 * ($csig1 * $ssig2) - $dn1 * ($ssig1 * $csig2) - $csig1 * $csig2 * $j12;
        }

        return [$s12b, $m12b, $m0x, $m12, $m21];
    }

    /**
     * Pin Geodesy.py:546-648 (_lambda12): one Newton/bisection refinement of
     * lambda12 plus the reduced-length derivative when $diffp is set.
     *
     * @param float $sbet1 Sine of the first reduced latitude.
     * @param float $cbet1 Cosine of the first reduced latitude.
     * @param float $dn1 First reduced-latitude denominator.
     * @param float $sbet2 Sine of the second reduced latitude.
     * @param float $cbet2 Cosine of the second reduced latitude.
     * @param float $dn2 Second reduced-latitude denominator.
     * @param float $salp1 Sine of the trial azimuth.
     * @param float $calp1 Cosine of the trial azimuth.
     * @param float $slam120 Sine of the initial longitude difference.
     * @param float $clam120 Cosine of the initial longitude difference.
     * @param bool $diffp True while the analytic derivative is still wanted.
     * @param array<int, float> $c1a C1 series scratch (untouched here).
     * @param array<int, float> $c2a C2 series scratch.
     * @param array<int, float> $c3a C3 series scratch, filled for this eps.
     * @param array<int, float> $a3x A3 coefficient table.
     * @param array<int, float> $c3x C3 coefficient table.
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float,
     *               6: float, 7: float, 8: float, 9: float, 10: float}
     *         (lam12, salp2, calp2, sig12, ssig1, csig1, ssig2, csig2, eps,
     *         domg12, dlam12).
     */
    private function lambda12(
        float $sbet1,
        float $cbet1,
        float $dn1,
        float $sbet2,
        float $cbet2,
        float $dn2,
        float $salp1,
        float $calp1,
        float $slam120,
        float $clam120,
        bool $diffp,
        array $c1a,
        array $c2a,
        array $c3a,
        array $a3x,
        array $c3x,
    ): array {
        if ($sbet1 === 0.0 && $calp1 === 0.0) {
            $calp1 = -self::tiny();
        }

        $salp0 = $salp1 * $cbet1;
        $calp0 = hypot($calp1, $salp1 * $sbet1);

        $ssig1 = $sbet1;
        $somg1 = $salp0 * $sbet1;
        $csig1 = $comg1 = $calp1 * $cbet1;
        [$ssig1, $csig1] = $this->norm($ssig1, $csig1);

        $salp2 = $cbet2 !== $cbet1 ? $salp0 / $cbet2 : $salp1;

        if ($cbet2 !== $cbet1 || abs($sbet2) !== -$sbet1) {
            $calp2 = sqrt(
                self::sq($calp1 * $cbet1)
                + (($cbet1 < -$sbet1)
                    ? ($cbet2 - $cbet1) * ($cbet1 + $cbet2)
                    : ($sbet1 - $sbet2) * ($sbet1 + $sbet2)),
            ) / $cbet2;
        } else {
            $calp2 = abs($calp1);
        }

        $ssig2 = $sbet2;
        $somg2 = $salp0 * $sbet2;
        $csig2 = $comg2 = $calp2 * $cbet2;
        [$ssig2, $csig2] = $this->norm($ssig2, $csig2);

        $sig12 = atan2(
            max(0.0, $csig1 * $ssig2 - $ssig1 * $csig2) + 0.0,
            $csig1 * $csig2 + $ssig1 * $ssig2,
        );

        $somg12 = max(0.0, $comg1 * $somg2 - $somg1 * $comg2) + 0.0;
        $comg12 = $comg1 * $comg2 + $somg1 * $somg2;
        $eta = atan2(
            $somg12 * $clam120 - $comg12 * $slam120,
            $comg12 * $clam120 + $somg12 * $slam120,
        );

        $k2 = self::sq($calp0) * self::EP2;
        $eps = $k2 / (2.0 * (1.0 + sqrt(1.0 + $k2)) + $k2);
        $c3a = $this->c3f($eps, $c3a, $c3x);
        $b312 = $this->sincosSeries($ssig2, $csig2, $c3a)
            - $this->sincosSeries($ssig1, $csig1, $c3a);
        $domg12 = -self::FLATTENING * $this->a3f($eps, $a3x) * $salp0 * ($sig12 + $b312);
        $lam12 = $eta + $domg12;

        if ($diffp) {
            if ($calp2 === 0.0) {
                $dlam12 = -2.0 * self::F1 * $dn1 / $sbet1;
            } else {
                // Pin: the derivative call passes _MASK_REDUCEDLENGTH only.
                $lengths = $this->lengths(
                    $eps,
                    $sig12,
                    $ssig1,
                    $csig1,
                    $dn1,
                    $ssig2,
                    $csig2,
                    $dn2,
                    $cbet1,
                    $cbet2,
                    false,
                    true,
                    $c1a,
                    $c2a,
                );
                $dlam12 = $lengths[1] * (self::F1 / ($calp2 * $cbet2));
            }
        } else {
            $dlam12 = NAN;
        }

        return [
            $lam12,
            $salp2,
            $calp2,
            $sig12,
            $ssig1,
            $csig1,
            $ssig2,
            $csig2,
            $eps,
            $domg12,
            $dlam12,
        ];
    }

    /**
     * Pin Geodesy.py:468-543 (_inverse_start): azimuth starting point.
     *
     * The pin takes a c1a/c2a scratch pair it never touches (upstream uses
     * them only for the reduced-length output this subset omits), so those
     * two parameters are dropped.
     *
     * @param float $sbet1 Sine of the first reduced latitude.
     * @param float $cbet1 Cosine of the first reduced latitude.
     * @param float $dn1 First reduced-latitude denominator.
     * @param float $sbet2 Sine of the second reduced latitude.
     * @param float $cbet2 Cosine of the second reduced latitude.
     * @param float $dn2 Second reduced-latitude denominator.
     * @param float $lam12 Longitude difference in radians.
     * @param float $slam12 Sine of the longitude difference.
     * @param float $clam12 Cosine of the longitude difference.
     * @param array<int, float> $a3x A3 coefficient table.
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     *         (sig12, salp1, calp1, salp2, calp2, dnm); sig12 < 0 signals the
     *         iterative path, salp2/calp2/dnm stay NAN when unused.
     */
    private function inverseStart(
        float $sbet1,
        float $cbet1,
        float $dn1,
        float $sbet2,
        float $cbet2,
        float $dn2,
        float $lam12,
        float $slam12,
        float $clam12,
        array $a3x,
    ): array {
        $sig12 = -1.0;
        $salp2 = NAN;
        $calp2 = NAN;
        $dnm = NAN;

        $sbet12 = $sbet2 * $cbet1 - $cbet2 * $sbet1;
        $cbet12 = $cbet2 * $cbet1 + $sbet2 * $sbet1;
        $sbet12a = $sbet2 * $cbet1;
        $sbet12a += $cbet2 * $sbet1;

        $shortline = $cbet12 >= 0.0 && $sbet12 < 0.5 && $cbet2 * $lam12 < 0.5;

        if ($shortline) {
            $sbetm2 = self::sq($sbet1 + $sbet2);
            $sbetm2 /= $sbetm2 + self::sq($cbet1 + $cbet2);
            $dnm = sqrt(1.0 + self::EP2 * $sbetm2);
            $omg12 = $lam12 / (self::F1 * $dnm);
            $somg12 = sin($omg12);
            $comg12 = cos($omg12);
        } else {
            $somg12 = $slam12;
            $comg12 = $clam12;
        }

        $salp1 = $cbet2 * $somg12;

        if ($comg12 >= 0.0) {
            $calp1 = $sbet12 + $cbet2 * $sbet1 * self::sq($somg12) / (1.0 + $comg12);
        } else {
            $calp1 = $sbet12a - $cbet2 * $sbet1 * self::sq($somg12) / (1.0 - $comg12);
        }

        $ssig12 = hypot($salp1, $calp1);
        $csig12 = $sbet1 * $sbet2 + $cbet1 * $cbet2 * $comg12;

        if ($shortline && $ssig12 < self::etol2()) {
            $salp2 = $cbet1 * $somg12;

            if ($comg12 >= 0.0) {
                $calp2 = $sbet12 - $cbet1 * $sbet2 * (self::sq($somg12) / (1.0 + $comg12));
            } else {
                $calp2 = $sbet12 - $cbet1 * $sbet2 * (1.0 - $comg12);
            }

            [$salp2, $calp2] = $this->norm($salp2, $calp2);
            $sig12 = atan2($ssig12, $csig12);
        } elseif (abs(self::N) >= 0.1 || $csig12 >= 0.0
            || $ssig12 >= 6.0 * abs(self::N) * M_PI * self::sq($cbet1)) {
            // Pin `pass`: keep the auxiliary-sphere azimuth as-is.
        } else {
            $lam12x = atan2(-$slam12, -$clam12);
            $k2 = self::sq($sbet1) * self::EP2;
            $eps = $k2 / (2.0 * (1.0 + sqrt(1.0 + $k2)) + $k2);
            $lamscale = self::FLATTENING * $cbet1 * $this->a3f($eps, $a3x) * M_PI;
            $betscale = $lamscale * $cbet1;
            $x = $lam12x / $lamscale;
            $y = $sbet12a / $betscale;

            if ($y > -self::TOL1 && $x > -1.0 - self::xthresh()) {
                $salp1 = min(1.0, -$x);
                $calp1 = -sqrt(1.0 - self::sq($salp1));
            } else {
                $k = $this->astroid($x, $y);
                $omg12a = $lamscale * (-$x * $k / (1.0 + $k));
                $somg12 = sin($omg12a);
                $comg12 = -cos($omg12a);
                $salp1 = $cbet2 * $somg12;
                $calp1 = $sbet12a - $cbet2 * $sbet1 * self::sq($somg12) / (1.0 - $comg12);
            }
        }

        if (! ($salp1 <= 0.0)) {
            [$salp1, $calp1] = $this->norm($salp1, $calp1);
        } else {
            $salp1 = 1.0;
            $calp1 = 0.0;
        }

        return [$sig12, $salp1, $calp1, $salp2, $calp2, $dnm];
    }

    /**
     * Pin Geodesy.py:405-406 (_a3f): A3 series evaluated at eps.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @param array<int, float> $a3x A3 coefficient table.
     * @return float A3(eps).
     */
    private function a3f(float $eps, array $a3x): float
    {
        return $this->polyval(self::N_A3 - 1, $a3x, 0, $eps);
    }

    /**
     * Pin Geodesy.py:409-416 (_c3f): fills the C3 series at eps.
     *
     * Note the offset walk is `o += m + 1` here, not `m + 2` like the other
     * coefficient helpers, and `o` is advanced BEFORE m is reused.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @param array<int, float> $c C3 series scratch, zero-filled and returned filled.
     * @param array<int, float> $c3x C3 coefficient table.
     * @return array<int, float> The filled C3 series (index 1..N_C3-1).
     */
    private function c3f(float $eps, array $c, array $c3x): array
    {
        $mult = 1.0;
        $o = 0;

        for ($lev = 1; $lev < self::N_C3; $lev++) {
            $m = self::N_C3 - $lev - 1;
            $mult *= $eps;
            $c[$lev] = $mult * $this->polyval($m, $c3x, $o, $eps);
            $o += $m + 1;
        }

        return $c;
    }

    /**
     * Pin Geodesy.py:367-371 (_a2m1f): A2 - 1 coefficient series.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @return float a2 - 1.
     */
    private function a2m1f(float $eps): float
    {
        $coeff = [-11.0, -28.0, -192.0, 0.0, 256.0];
        $m = intdiv(self::N_A2, 2);
        $t = $this->polyval($m, $coeff, 0, self::sq($eps)) / $this->coeffAt($coeff, $m + 1);

        return ($t - $eps) / (1.0 + $eps);
    }

    /**
     * Pin Geodesy.py:374-402 (_c2f): fills the C2 series at eps.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @param array<int, float> $c C2 series scratch, zero-filled and returned filled.
     * @return array<int, float> The filled C2 series (index 1..N_C2).
     */
    private function c2f(float $eps, array $c): array
    {
        $coeff = [
            1.0, 2.0, 16.0, 32.0, 35.0, 64.0, 384.0, 2048.0, 15.0, 80.0, 768.0, 7.0, 35.0, 512.0, 63.0, 1280.0, 77.0, 2048.0,
        ];
        $eps2 = self::sq($eps);
        $d = $eps;
        $o = 0;

        for ($lev = 1; $lev <= self::N_C2; $lev++) {
            $m = intdiv(self::N_C2 - $lev, 2);
            $c[$lev] = $d * $this->polyval($m, $coeff, $o, $eps2) / $this->coeffAt($coeff, $o + $m + 1);
            $o += $m + 2;
            $d *= $eps;
        }

        return $c;
    }

    /**
     * Pin Geodesy.py:329-333 (_a1m1f): A1 - 1 coefficient series.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @return float a1 - 1.
     */
    private function a1m1f(float $eps): float
    {
        $coeff = [1.0, 4.0, 64.0, 0.0, 256.0];
        $m = intdiv(self::N_A1, 2);
        $t = $this->polyval($m, $coeff, 0, self::sq($eps)) / $this->coeffAt($coeff, $m + 1);

        return ($t + $eps) / (1.0 - $eps);
    }

    /**
     * Pin Geodesy.py:336-364 (_c1f): fills the C1 series at eps.
     *
     * @param float $eps Auxiliary-sphere eccentricity.
     * @param array<int, float> $c C1 series scratch, zero-filled and returned filled.
     * @return array<int, float> The filled C1 series (index 1..N_C1).
     */
    private function c1f(float $eps, array $c): array
    {
        // Coefficient digits are transcribed row-for-row from the pin; the
        // Python ints are written as floats here because every use is a true
        // division and PHPStan's strict rules reject a possibly-zero
        // integer divisor (values are unchanged).
        $coeff = [
            -1.0, 6.0, -16.0, 32.0, -9.0, 64.0, -128.0, 2048.0, 9.0, -16.0, 768.0, 3.0, -5.0, 512.0, -7.0, 1280.0, -7.0, 2048.0,
        ];
        $eps2 = self::sq($eps);
        $d = $eps;
        $o = 0;

        for ($lev = 1; $lev <= self::N_C1; $lev++) {
            $m = intdiv(self::N_C1 - $lev, 2);
            $c[$lev] = $d * $this->polyval($m, $coeff, $o, $eps2) / $this->coeffAt($coeff, $o + $m + 1);
            $o += $m + 2;
            $d *= $eps;
        }

        return $c;
    }

    /**
     * Pin Geodesy.py:87-97 (_a3_coeff): the _A3X table (upstream
     * GEOGRAPHICLIB_GEODESIC_ORDER A3 coefficients), length _N_A3X = 6.
     *
     * The pin builds this at import time; PHP computes it per solve, which
     * is identical arithmetic on the same frozen inputs.
     *
     * @return array<int, float> The six A3 coefficients.
     */
    private function a3Coeff(): array
    {
        $coeff = [-3.0, 128.0, -2.0, -3.0, 64.0, -1.0, -3.0, -1.0, 16.0, 3.0, -1.0, -2.0, 8.0, 1.0, -1.0, 2.0, 1.0, 1.0];
        $table = array_fill(0, self::N_A3X, 0.0);
        $o = 0;
        $k = 0;

        for ($j = self::N_A3 - 1; $j >= 0; $j--) {
            $m = min(self::N_A3 - $j - 1, $j);
            $table[$k] = $this->polyval($m, $coeff, $o, self::N) / $this->coeffAt($coeff, $o + $m + 1);
            $k += 1;
            $o += $m + 2;
        }

        return $table;
    }

    /**
     * Pin Geodesy.py:100-157 (_c3_coeff): the _C3X table, length _N_C3X = 15.
     *
     * @return array<int, float> The fifteen C3 coefficients.
     */
    private function c3Coeff(): array
    {
        $coeff = [
            3.0, 128.0, 2.0, 5.0, 128.0, -1.0, 3.0, 3.0, 64.0, -1.0, 0.0, 1.0, 8.0, -1.0,
            1.0, 4.0, 5.0, 256.0, 1.0, 3.0, 128.0, -3.0, -2.0, 3.0, 64.0, 1.0, -3.0, 2.0,
            32.0, 7.0, 512.0, -10.0, 9.0, 384.0, 5.0, -9.0, 5.0, 192.0, 7.0, 512.0, -14.0,
            7.0, 512.0, 21.0, 2560.0,
        ];
        $table = array_fill(0, self::N_C3X, 0.0);
        $o = 0;
        $k = 0;

        for ($lev = 1; $lev < self::N_C3; $lev++) {
            for ($j = self::N_C3 - 1; $j >= $lev; $j--) {
                $m = min(self::N_C3 - $j - 1, $j);
                $table[$k] = $this->polyval($m, $coeff, $o, self::N) / $this->coeffAt($coeff, $o + $m + 1);
                $k += 1;
                $o += $m + 2;
            }
        }

        return $table;
    }

    /**
     * Pin Geodesy.py:302-326 (_astroid): the astroid-latitude seed.
     *
     * @param float $x Reduced longitude step.
     * @param float $y Reduced latitude step.
     * @return float Astroid k for the near-antipodal start.
     */
    private function astroid(float $x, float $y): float
    {
        $p = self::sq($x);
        $q = self::sq($y);
        $r = ($p + $q - 1.0) / 6.0;

        if (! ($q === 0.0 && $r <= 0.0)) {
            $s = $p * $q / 4.0;
            $r2 = self::sq($r);
            $r3 = $r * $r2;
            $disc = $s * ($s + 2.0 * $r3);
            $u = $r;

            if ($disc >= 0.0) {
                $t3 = $s + $r3;
                $t3 += $t3 < 0.0 ? -sqrt($disc) : sqrt($disc);
                $t = $this->cbrt($t3);
                $u += $t + ($t !== 0.0 ? $r2 / $t : 0.0);
            } else {
                $ang = atan2(sqrt(-$disc), -($s + $r3));
                $u += 2.0 * $r * cos($ang / 3.0);
            }

            $v = sqrt(self::sq($u) + $q);
            $uv = $u < 0.0 ? $q / ($v - $u) : $u + $v;
            $w = ($uv - $q) / (2.0 * $v);
            $k = $uv / (sqrt($uv + self::sq($w)) + $w);
        } else {
            $k = 0.0;
        }

        return $k;
    }

    /**
     * Pin Geodesy.py:280-299 (_sincos_series): Clenshaw sin-series sum.
     *
     * Only the sin branch is kept: the pin's cos branch serves the omitted
     * area computation.
     *
     * @param float $sinx Sine of the argument.
     * @param float $cosx Cosine of the argument.
     * @param array<int, float> $c Series coefficients.
     * @return float Series value.
     */
    private function sincosSeries(float $sinx, float $cosx, array $c): float
    {
        $k = count($c);
        $n = $k - 1;
        $ar = 2.0 * ($cosx - $sinx) * ($cosx + $sinx);
        $y1 = 0.0;

        if (($n & 1) === 1) {
            $k -= 1;
            $y0 = $c[$k];
        } else {
            $y0 = 0.0;
        }

        $n = intdiv($n, 2);

        while ($n) {
            $n -= 1;
            $k -= 1;
            $y1 = $ar * $y0 - $y1 + $c[$k];
            $k -= 1;
            $y0 = $ar * $y1 - $y0 + $c[$k];
        }

        return 2.0 * $sinx * $cosx * $y0;
    }

    /**
     * Pin Geodesy.py:217-234 (_sincosd): sine/cosine of a degree angle.
     *
     * The `c + 0.0` normalizes a -0.0 cosine; `q % 4` is a Python (always
     * non-negative) modulo, so PHP's truncating `%` needs the +4 correction.
     *
     * @param float $x Angle in degrees.
     * @return array{0: float, 1: float} (sin, cos).
     */
    private function sincosd(float $x): array
    {
        $r = is_finite($x) ? fmod($x, 360.0) : NAN;
        $q = is_nan($r) ? 0 : $this->roundHalfEven($r / 90.0);
        $r -= 90.0 * $q;
        $r = deg2rad($r);
        $s = sin($r);
        $c = cos($r);

        $q = $q % 4;

        if ($q < 0) {
            $q += 4;
        }

        if ($q === 1) {
            [$s, $c] = [$c, -$s];
        } elseif ($q === 2) {
            [$s, $c] = [-$s, -$c];
        } elseif ($q === 3) {
            [$s, $c] = [-$c, $s];
        }

        $c = $c + 0.0;

        if ($s === 0.0) {
            $s = $this->copysign($s, $x);
        }

        return [$s, $c];
    }

    /**
     * Pin Geodesy.py:237-253 (_sincosde): sine/cosine with an exact residual.
     *
     * @param float $x Angle in degrees.
     * @param float $t Residual correction in degrees.
     * @return array{0: float, 1: float} (sin, cos).
     */
    private function sincosde(float $x, float $t): array
    {
        $q = is_finite($x) ? $this->roundHalfEven($x / 90.0) : 0;
        $r = $x - 90.0 * $q;
        $r = deg2rad($this->angRound($r + $t));
        $s = sin($r);
        $c = cos($r);

        $q = $q % 4;

        if ($q < 0) {
            $q += 4;
        }

        if ($q === 1) {
            [$s, $c] = [$c, -$s];
        } elseif ($q === 2) {
            [$s, $c] = [-$s, -$c];
        } elseif ($q === 3) {
            [$s, $c] = [-$c, $s];
        }

        $c = $c + 0.0;

        if ($s === 0.0) {
            $s = $this->copysign($s, $x);
        }

        return [$s, $c];
    }

    /**
     * Pin Geodesy.py:256-272 (_atan2d): atan2 returning degrees.
     *
     * @param float $y Numerator.
     * @param float $x Denominator.
     * @return float Angle in degrees.
     */
    private function atan2d(float $y, float $x): float
    {
        if (abs($y) > abs($x)) {
            $q = 2;
            [$x, $y] = [$y, $x];
        } else {
            $q = 0;
        }

        if ($x < 0.0) {
            $q += 1;
            $x = -$x;
        }

        $ang = rad2deg(atan2($y, $x));

        if ($q === 1) {
            $ang = $this->copysign(180.0, $y) - $ang;
        } elseif ($q === 2) {
            $ang = 90.0 - $ang;
        } elseif ($q === 3) {
            $ang = -90.0 + $ang;
        }

        return $ang;
    }

    /**
     * Pin Geodesy.py:209-214 (_ang_diff): exact longitude difference.
     *
     * @param float $x First longitude in degrees.
     * @param float $y Second longitude in degrees.
     * @return array{0: float, 1: float} (difference, residual).
     */
    private function angDiff(float $x, float $y): array
    {
        [$d, $t] = $this->esum($this->remainder(-$x, 360.0), $this->remainder($y, 360.0));
        [$d, $t] = $this->esum($this->remainder($d, 360.0), $t);

        if ($d === 0.0 || abs($d) === 180.0) {
            $d = $this->copysign($d, $t === 0.0 ? $y - $x : -$t);
        }

        return [$d, $t];
    }

    /**
     * Pin Geodesy.py:201-206 (_ang_round): degrees rounded to multiples of 1/16.
     *
     * @param float $x Angle in degrees.
     * @return float Rounded angle.
     */
    private function angRound(float $x): float
    {
        $z = 1.0 / 16.0;
        $y = abs($x);

        if ($y < $z) {
            $y = $z - ($z - $y);
        }

        return $this->copysign($y, $x);
    }

    /**
     * Read one entry of a coefficient table (pin indexes its list directly).
     *
     * The accessor keeps the divisor type a general float, which the strict
     * rules require: a table literal that happens to contain a zero entry
     * would otherwise make every `/ coeff[...]` a flagged division by zero.
     *
     * @param array<int, float> $coeff Coefficient table.
     * @param int $index Index into the table.
     * @return float The coefficient at that index.
     */
    private function coeffAt(array $coeff, int $index): float
    {
        return $coeff[$index];
    }

    /**
     * Pin Geodesy.py:192-198 (_polyval): Horner walk of a coefficient table.
     *
     * Integer index arithmetic throughout: $s advances by 1 per step and
     * $n counts down, so the leading-coefficient offset is preserved exactly
     * as in Python (a float counter here would break the table offset).
     *
     * @param int $n Polynomial order, negative reads only the leading term.
     * @param array<int, float|int> $p Coefficient table.
     * @param int $s Start offset into $p.
     * @param float $x Evaluation point.
     * @return float Polynomial value.
     */
    private function polyval(int $n, array $p, int $s, float $x): float
    {
        $y = $n < 0 ? 0.0 : $this->coeffAt($p, $s);

        while ($n > 0) {
            $n -= 1;
            $s += 1;
            $y = $y * $x + $this->coeffAt($p, $s);
        }

        return $y;
    }

    /**
     * Pin Geodesy.py:182-189 (_esum): exact two-sum (Knuth/Shewchuk).
     *
     * @param float $u First summand.
     * @param float $v Second summand.
     * @return array{0: float, 1: float} (sum, residual).
     */
    private function esum(float $u, float $v): array
    {
        $s = $u + $v;
        $up = $s - $v;
        $vpp = $s - $up;
        $up -= $u;
        $vpp -= $v;
        $t = $s === 0.0 ? $s : 0.0 - ($up + $vpp);

        return [$s, $t];
    }

    /**
     * Pin Geodesy.py:177-179 (_norm): normalise a 2-vector to unit length.
     *
     * @param float $x First component.
     * @param float $y Second component.
     * @return array{0: float, 1: float} Unit vector.
     */
    private function norm(float $x, float $y): array
    {
        $r = hypot($x, $y);

        return [$x / $r, $y / $r];
    }

    /**
     * math.copysign: magnitude of x with the sign of y (PHP has no built-in).
     *
     * `fdiv` is used because plain `1.0 / -0.0` raises DivisionByZeroError in
     * PHP 8 while the IEEE result -INF is exactly the sign test wanted here.
     *
     * @param float $x Magnitude donor.
     * @param float $y Sign donor.
     * @return float The magnitude of x carrying the sign bit of y.
     */
    private function copysign(float $x, float $y): float
    {
        return $y < 0.0 || fdiv(1.0, $y) < 0.0 ? -abs($x) : abs($x);
    }

    /**
     * Pin Geodesy.py:173-174 (_cbrt): signed real cube root.
     *
     * @param float $x Value.
     * @return float Cube root of x, sign preserved.
     */
    private function cbrt(float $x): float
    {
        return $this->copysign(pow(abs($x), 1.0 / 3.0), $x);
    }

    /**
     * Pin Geodesy.py:169-170 (_sq): square.
     *
     * @param float $x Value.
     * @return float x * x.
     */
    private static function sq(float $x): float
    {
        return $x * $x;
    }

    /**
     * math.remainder (IEEE 754, Python `math.remainder`) in degrees.
     *
     * This is NOT fmod: the quotient is rounded to nearest with ties to
     * even, which is what _ang_diff relies on to keep longitude differences
     * exact. Half-way cases only occur at whole multiples of 180 degrees.
     *
     * @param float $x Dividend in degrees.
     * @param float $y Divisor in degrees (360.0 at every call site).
     * @return float x - n * y with n the nearest even-quotient integer.
     */
    private function remainder(float $x, float $y): float
    {
        $r = fmod($x, $y);
        $half = $y / 2.0;
        $abs = abs($r);

        if ($abs > $half) {
            return $r > 0.0 ? $r - $y : $r + $y;
        }

        if ($abs < $half) {
            return $r;
        }

        // Exact half-way: keep the even quotient, which is the truncated
        // quotient fmod already produced when it is even.
        $quotient = ($x - $r) / $y;

        return fmod($quotient, 2.0) === 0.0 ? $r : ($r > 0.0 ? $r - $y : $r + $y);
    }

    /**
     * Python round(): nearest integer, ties to even.
     *
     * PHP's round() breaks ties away from zero, so the tie is resolved
     * explicitly as CPython does (2 * round(x / 2) for an exact half).
     *
     * @param float $x Value.
     * @return int Nearest integer, ties to even.
     */
    private function roundHalfEven(float $x): int
    {
        $rounded = round($x);

        if (abs($x - $rounded) === 0.5) {
            $rounded = 2.0 * round($x / 2.0);
        }

        return (int) $rounded;
    }
}
