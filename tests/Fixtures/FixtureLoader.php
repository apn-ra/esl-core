<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Fixtures;

use RuntimeException;

/**
 * Loads raw fixture files from the Fixtures directory.
 *
 * Fixture files are byte-accurate representations of ESL protocol messages.
 * They use LF (\n) line endings. They must not be modified by editors that
 * normalize line endings.
 *
 * @internal Test support only.
 */
final class FixtureLoader
{
    private static string $fixtureRoot;

    public static function root(): string
    {
        if (!isset(self::$fixtureRoot)) {
            self::$fixtureRoot = __DIR__;
        }

        return self::$fixtureRoot;
    }

    /**
     * Load a raw fixture file as a string.
     *
     * @throws RuntimeException if the file does not exist.
     */
    public static function load(string $relativePath): string
    {
        $path = self::root() . '/' . ltrim($relativePath, '/');

        if (!is_file($path)) {
            throw new RuntimeException("Fixture file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read fixture file: {$path}");
        }

        return $contents;
    }

    /**
     * Load a fixture file and assert it ends with \n\n (header terminator).
     */
    public static function loadFrame(string $relativePath): string
    {
        $contents = self::load($relativePath);

        if (!str_contains($contents, "\n\n")) {
            throw new RuntimeException(
                "Fixture does not contain \\n\\n header terminator: {$relativePath}"
            );
        }

        return $contents;
    }

    /**
     * Split a raw fixture file containing multiple concatenated frames.
     *
     * This is useful for multi-frame sequence fixtures that document
     * a complete auth + subscribe + event flow.
     */
    public static function loadSequence(string $relativePath): array
    {
        // Delegate actual splitting to the FrameParser — fixture sequences
        // should be loaded through the parser, not split manually here.
        // This method exists to document the intent; tests use EslFixtureBuilder
        // to produce deterministic sequences programmatically.
        throw new RuntimeException(
            'loadSequence is not implemented. Use EslFixtureBuilder to build sequences ' .
            'and feed them through FrameParser in tests.'
        );
    }
}
