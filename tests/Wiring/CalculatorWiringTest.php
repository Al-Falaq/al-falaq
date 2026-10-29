<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Wiring;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\Strategies\SeventhOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\SunnahTimes;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Wiring smoke tests: every service is constructed with plain `new` / named
 * factories, and every PHP block in `docs/integrations.md` is extracted and
 * executed verbatim. No container, no service provider, no config files — the
 * library must be usable in a single expression.
 */
final class CalculatorWiringTest extends TestCase
{
    public function testQiblaDefaultWiresSphericalGeodesy(): void
    {
        self::assertInstanceOf(SphericalCalculator::class, QiblaCalculator::default()->geodesy());
    }

    public function testQiblaWgs84WiresKarneyGeodesy(): void
    {
        self::assertInstanceOf(Wgs84KarneyCalculator::class, QiblaCalculator::wgs84()->geodesy());
    }

    public function testPrayerCalculatorSatisfiesItsContract(): void
    {
        self::assertInstanceOf(PrayerTimesCalculatorInterface::class, PrayerTimesCalculator::default());
    }

    public function testDefaultCalculatorWiresRealDayEngineBehindPort(): void
    {
        $solar = PrayerTimesCalculator::default()->solar();

        self::assertInstanceOf(SolarDayEngine::class, $solar);
        self::assertInstanceOf(MeanSolarCalculator::class, $solar);
    }

    public function testResolverAndTimetableSatisfyTheirContracts(): void
    {
        self::assertInstanceOf(NextPrayerResolverInterface::class, new NextPrayerResolver());
        self::assertInstanceOf(TimetableServiceInterface::class, new TimetableService());
    }

    public function testDefaultForMapsRulesToStrategies(): void
    {
        $mwl = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $calc = PrayerTimesCalculator::defaultFor($mwl);
        self::assertInstanceOf(MiddleOfNightCorrection::class, $calc->highLatitudeCorrection());
        self::assertInstanceOf(NearestLatitudeHandler::class, $calc->polarCircleHandler());
        self::assertInstanceOf(SeasonAdjustedTwilight::class, $calc->twilightModel());

        $tehran = CalculationMethod::TEHRAN->defaultParameters();
        self::assertInstanceOf(NearestLatitudeHandler::class, PrayerTimesCalculator::defaultFor($tehran)->polarCircleHandler());

        $none = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::SEVENTH_OF_NIGHT,
            PolarCircleRule::MAKKAH,
            18.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
        $custom = PrayerTimesCalculator::defaultFor($none);
        self::assertInstanceOf(SeventhOfNightCorrection::class, $custom->highLatitudeCorrection());
        self::assertInstanceOf(MakkahHandler::class, $custom->polarCircleHandler());
    }

    public function testOutputShapesMatchDocumentedSchema(): void
    {
        // Mirrors docs/integrations.md output shapes: keys, order, formats.
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 3, 1),
            CalculationMethod::SINGAPORE->defaultParameters(),
        );
        $keys = ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'];

        self::assertSame($keys, array_keys($schedule->all()));
        self::assertSame($keys, array_keys($schedule->toArray()));

        foreach ($schedule->toArray() as $iso) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $iso);
        }

        $zoned = $schedule->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'))->toArray();

        self::assertSame($keys, array_keys($zoned));

        foreach ($zoned as $iso) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+08:00$/', $iso);
        }
    }

    /**
     * Section heading → variables the snippet must define and their types.
     *
     * @var array<string, array<string, class-string|'array'>>
     */
    private const SECTION_EXPECTATIONS = [
        'Prayer times' => [
            'calculator' => PrayerTimesCalculator::class,
            'schedule' => PrayerSchedule::class,
        ],
        'Prayer times — explicit strategy wiring' => [
            'calculator' => PrayerTimesCalculator::class,
        ],
        'Qibla' => [
            'spherical' => QiblaCalculator::class,
            'precise' => QiblaCalculator::class,
            'result' => QiblaResult::class,
            'preciseResult' => QiblaResult::class,
        ],
        'Timetable' => [
            'month' => 'array',
        ],
        'Sunnah' => [
            'sunnah' => SunnahTimes::class,
        ],
        'Crescent visibility' => [
            'geometry' => CrescentGeometry::class,
            'verdicts' => 'array',
        ],
        'Hijri conversion' => [
            'hijri' => HijriDate::class,
            'hijriAtSunset' => HijriDate::class,
        ],
        'Clock input (edges only)' => [
            'next' => NextPrayer::class,
        ],
    ];

    /**
     * Every PHP block in docs/integrations.md runs exactly as written.
     *
     * The doc is the source of truth for wiring: extracting and executing its
     * fenced blocks means a renamed constructor, a changed signature, or an
     * unqualified global class in a snippet fails the build. Section headings
     * are the anchors, so moving a snippet to the wrong section fails too.
     */
    public function testDocumentedSnippetsRunAsWritten(): void
    {
        $markdown = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/integrations.md');
        $snippets = self::phpSnippets($markdown);

        self::assertGreaterThanOrEqual(
            count(self::SECTION_EXPECTATIONS),
            count($snippets),
            'Expected at least one PHP snippet per documented wiring recipe.',
        );

        $bySection = [];

        foreach ($snippets as [$section, $code]) {
            $bySection[$section] = array_merge($bySection[$section] ?? [], self::runSnippet($code));
        }

        foreach (self::SECTION_EXPECTATIONS as $section => $expectations) {
            self::assertArrayHasKey($section, $bySection, sprintf('Missing documented section: %s', $section));

            foreach ($expectations as $variable => $type) {
                self::assertArrayHasKey(
                    $variable,
                    $bySection[$section],
                    sprintf('Section "%s" must define $%s.', $section, $variable),
                );

                if ($type === 'array') {
                    self::assertIsArray($bySection[$section][$variable]);
                } else {
                    self::assertInstanceOf($type, $bySection[$section][$variable]);
                }
            }
        }

        $prayer = $bySection['Prayer times'];
        $explicit = $bySection['Prayer times — explicit strategy wiring'];
        $qibla = $bySection['Qibla'];
        $hijri = $bySection['Hijri conversion'];
        $clock = $bySection['Clock input (edges only)'];

        $timetable = $bySection['Timetable'];
        $crescent = $bySection['Crescent visibility'];

        $month = $timetable['month'] ?? null;
        self::assertIsArray($month);
        self::assertCount(31, $month);

        foreach ($month as $day) {
            self::assertInstanceOf(PrayerSchedule::class, $day);
        }

        $verdicts = $crescent['verdicts'] ?? null;
        self::assertIsArray($verdicts);
        self::assertSame(
            ['yallop', 'odeh', 'mabims1992', 'neomabims2021'],
            array_keys($verdicts),
        );

        $schedule = $prayer['schedule'] ?? null;
        self::assertInstanceOf(PrayerSchedule::class, $schedule);
        self::assertSame(
            ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'],
            array_keys($schedule->all()),
        );

        $explicitCalculator = $explicit['calculator'] ?? null;
        self::assertInstanceOf(PrayerTimesCalculator::class, $explicitCalculator);
        self::assertInstanceOf(MeanSolarCalculator::class, $explicitCalculator->solar());
        self::assertInstanceOf(MiddleOfNightCorrection::class, $explicitCalculator->highLatitudeCorrection());
        self::assertInstanceOf(NearestLatitudeHandler::class, $explicitCalculator->polarCircleHandler());
        self::assertInstanceOf(SeasonAdjustedTwilight::class, $explicitCalculator->twilightModel());

        $spherical = $qibla['spherical'] ?? null;
        self::assertInstanceOf(QiblaCalculator::class, $spherical);
        self::assertInstanceOf(SphericalCalculator::class, $spherical->geodesy());

        $precise = $qibla['precise'] ?? null;
        self::assertInstanceOf(QiblaCalculator::class, $precise);
        self::assertInstanceOf(Wgs84KarneyCalculator::class, $precise->geodesy());

        $hijriDate = $hijri['hijri'] ?? null;
        $hijriAtSunset = $hijri['hijriAtSunset'] ?? null;
        self::assertInstanceOf(HijriDate::class, $hijriDate);
        self::assertInstanceOf(HijriDate::class, $hijriAtSunset);
        self::assertNotSame($hijriDate->isoformat(), $hijriAtSunset->isoformat());

        $next = $clock['next'] ?? null;
        self::assertInstanceOf(NextPrayer::class, $next);
        self::assertSame('UTC', $next->at->getTimezone()->getName());
        self::assertGreaterThan(0, $next->inSeconds);
    }

    /**
     * The parser ignores headings inside any fence and reads only php blocks.
     *
     * @return void
     */
    public function testPhpSnippetParserRespectsFenceState(): void
    {
        $markdown = <<<'MARKDOWN'
## First
```text
## not a heading
```

## Second
```PHP
$answer = 42;
```
```php
$other = 'x';
```
MARKDOWN;

        self::assertSame([
            ['Second', '$answer = 42;' . "\n"],
            ['Second', '$other = \'x\';' . "\n"],
        ], self::phpSnippets($markdown));
    }

    /**
     * Tilde, indented, and unterminated fences all reach the snippet list.
     *
     * The parser's docblock promises all three; the fence-state test above
     * covers only column-0 backticks, so exercise the remaining branches.
     *
     * @return void
     */
    public function testPhpSnippetParserHandlesTildeIndentedAndUnterminatedFences(): void
    {
        $markdown = <<<'MARKDOWN'
## Tilde
~~~php
$tilde = true;
~~~

## Indented
   ```php
$indented = true;
   ```

## Unterminated
```php
$open = true;
MARKDOWN;

        self::assertSame([
            ['Tilde', '$tilde = true;' . "\n"],
            ['Indented', '$indented = true;' . "\n"],
            ['Unterminated', '$open = true;' . "\n"],
        ], self::phpSnippets($markdown));
    }

    /**
     * Fenced PHP blocks paired with their preceding `## ` heading.
     *
     * Tracks every fence (backtick or tilde), so `## ` lines inside any code
     * block are ignored; only `php`-tagged fences become snippets. Supports
     * up to three spaces of fence indentation and case-insensitive `php`.
     * An unterminated PHP fence is surfaced as a snippet so eval fails loudly;
     * a `## ` heading must anchor one snippet per documented recipe.
     *
     * @param string $markdown Full docs/integrations.md contents.
     * @return list<array{string, string}> Heading + code pairs, in file order.
     */
    private static function phpSnippets(string $markdown): array
    {
        $snippets = [];
        $section = '';
        $buffer = null;
        $fence = null;
        $isPhp = false;
        $lines = preg_split('/\R/', $markdown);

        foreach ($lines === false ? [] : $lines as $line) {
            if ($fence === null) {
                if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $matches) === 1) {
                    $fence = $matches[1];
                    $isPhp = preg_match('/^php\b/i', trim($matches[2])) === 1;

                    if ($isPhp) {
                        $buffer = '';
                    }

                    continue;
                }

                if (str_starts_with($line, '## ')) {
                    $section = rtrim(substr($line, 3));
                }

                continue;
            }

            if (preg_match('/^ {0,3}' . preg_quote($fence, '/') . '/', $line) === 1) {
                if ($isPhp) {
                    $snippets[] = [$section, $buffer ?? ''];
                }

                $buffer = null;
                $fence = null;
                $isPhp = false;

                continue;
            }

            if ($isPhp && $buffer !== null) {
                $buffer .= $line . "\n";
            }
        }

        if ($isPhp) {
            $snippets[] = [$section, $buffer ?? ''];
        }

        return $snippets;
    }

    /**
     * Evaluate one snippet and return the variables it defined.
     *
     * Snippets are self-contained: every class is imported or fully
     * qualified, and every input is constructed in the block. The runner
     * adds `strict_types=1`, so scalar argument drift fails here rather than
     * only in strict consumer files; snippets must not declare it themselves.
     *
     * The namespace prefix is load-bearing: eval() otherwise runs in the
     * global namespace, where unqualified globals (e.g. DateTimeImmutable)
     * resolve and hide the drift this test guards against.
     *
     * @param string $code PHP code without the opening tag.
     * @return array<string, mixed> Variables in scope after execution.
     */
    private static function runSnippet(string $code): array
    {
        eval("declare(strict_types=1);\nnamespace AlFalaq\\AlFalaq\\Tests\\Wiring;\n\n" . $code);
        unset($code);

        return get_defined_vars();
    }
}
