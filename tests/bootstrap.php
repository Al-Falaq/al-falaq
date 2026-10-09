<?php

declare(strict_types=1);

// PHPUnit bootstrap: deterministic timezone for all tests.
// Reproducible seeds (e.g. Sweep) are set inline per test, not globally.
date_default_timezone_set('UTC');
