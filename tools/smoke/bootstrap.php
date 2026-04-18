<?php

declare(strict_types=1);

/**
 * Resolve the nearest legitimate Composer autoloader for smoke helpers.
 *
 * Supports:
 * - running inside the esl-core repository (`<repo>/vendor/autoload.php`)
 * - running from a Composer-installed package (`<consumer>/vendor/autoload.php`)
 */
function resolveSmokeHelperAutoloadPath(string $smokeDir): string
{
    $candidates = [
        dirname($smokeDir, 2) . '/vendor/autoload.php',
        dirname($smokeDir, 4) . '/autoload.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException(sprintf(
        'Unable to locate Composer autoload.php for smoke helper at "%s". Checked: %s',
        $smokeDir,
        implode(', ', $candidates),
    ));
}
