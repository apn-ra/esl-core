<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Protocol;

use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\ParseException;

/**
 * Immutable collection of ESL protocol headers.
 *
 * Headers are stored and accessed case-insensitively. Original header name
 * casing is preserved for serialization purposes. When a header appears
 * multiple times, all values are preserved and accessible.
 *
 * Header values are stored RAW (not URL-decoded). Consumers that require
 * decoded values (e.g., text/event-plain event headers) must decode via
 * urldecode() externally, or use NormalizedEvent which handles decoding.
 *
 * @api
 */
final class HeaderBag
{
    /**
     * Headers indexed by lowercase name.
     *
     * @var array<string, array{name: string, values: list<string>}>
     */
    private readonly array $headers;

    /**
     * Headers in their original flat insertion order, including repeats.
     *
     * @var list<array{key: string, name: string, value: string}>
     */
    private readonly array $flatHeaders;

    /**
     * @param array<string, array{name: string, values: list<string>}> $headers
     * @param list<array{key: string, name: string, value: string}> $flatHeaders
     */
    private function __construct(array $headers, array $flatHeaders)
    {
        $this->headers = $headers;
        $this->flatHeaders = $flatHeaders;
    }

    /**
     * Parse a raw header block.
     *
     * The block should NOT include the trailing blank line (\n\n).
     * Lines are separated by \n. \r\n is also tolerated.
     *
     * @throws ParseException if a header line has no colon separator.
     */
    public static function fromHeaderBlock(string $block): self
    {
        if ($block === '') {
            return new self([], []);
        }

        $headers = [];
        $flatHeaders = [];

        foreach (explode("\n", $block) as $line) {
            $line = rtrim($line, "\r");

            if ($line === '') {
                continue;
            }

            // Find ': ' separator (standard)
            $colonPos = strpos($line, ': ');
            if ($colonPos !== false) {
                $name  = substr($line, 0, $colonPos);
                $value = substr($line, $colonPos + 2);
            } else {
                // Tolerate ':' without trailing space (non-standard but real)
                $colonPos = strpos($line, ':');
                if ($colonPos === false) {
                    throw new MalformedFrameException(
                        "Malformed header line (no colon separator): {$line}"
                    );
                }
                $name  = substr($line, 0, $colonPos);
                $value = ltrim(substr($line, $colonPos + 1));
            }

            self::assertValidHeaderName($name, $line);

            $key = strtolower($name);
            if (!isset($headers[$key])) {
                $headers[$key] = ['name' => $name, 'values' => []];
            }
            $headers[$key]['values'][] = $value;
            $flatHeaders[] = ['key' => $key, 'name' => $name, 'value' => $value];
        }

        return new self($headers, $flatHeaders);
    }

    /**
     * Get the first value for a header (case-insensitive lookup).
     * Returns null if the header is not present.
     */
    public function get(string $name): ?string
    {
        return $this->headers[strtolower($name)]['values'][0] ?? null;
    }

    /**
     * Get all values for a header (handles repeated headers).
     *
     * @return list<string>
     */
    public function all(string $name): array
    {
        return $this->headers[strtolower($name)]['values'] ?? [];
    }

    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Returns all lowercase header names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->headers);
    }

    /**
     * Returns all headers as name => first-value pairs, preserving original casing.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->headers as $entry) {
            $result[$entry['name']] = $entry['values'][0];
        }
        return $result;
    }

    /**
     * Returns all headers including repeated headers, in insertion order.
     *
     * @return list<array{name: string, value: string}>
     */
    public function toFlatArray(): array
    {
        return array_map(
            static fn(array $entry): array => [
                'name' => $entry['name'],
                'value' => $entry['value'],
            ],
            $this->flatHeaders,
        );
    }

    /**
     * Number of distinct header names (not counting repeated values).
     */
    public function count(): int
    {
        return count($this->headers);
    }

    public function isEmpty(): bool
    {
        return empty($this->headers);
    }

    /**
     * Return a new HeaderBag with the given header added or replaced.
     *
     * @throws ParseException if the header name is empty or has surrounding whitespace.
     */
    public function with(string $name, string $value): self
    {
        self::assertValidHeaderName($name);

        $key     = strtolower($name);
        $headers = $this->headers;
        $flatHeaders = array_values(array_filter(
            $this->flatHeaders,
            static fn(array $entry): bool => $entry['key'] !== $key
        ));

        $headers[$key] = ['name' => $name, 'values' => [$value]];
        $flatHeaders[] = ['key' => $key, 'name' => $name, 'value' => $value];

        return new self($headers, $flatHeaders);
    }

    private static function assertValidHeaderName(string $name, ?string $line = null): void
    {
        if (trim($name) === '') {
            if ($line !== null) {
                throw new MalformedFrameException(
                    "Malformed header line (empty header name): {$line}"
                );
            }

            throw new MalformedFrameException('Invalid header name: header name must not be empty');
        }

        if ($name !== trim($name)) {
            if ($line !== null) {
                throw new MalformedFrameException(
                    "Malformed header line (header name contains surrounding whitespace): {$line}"
                );
            }

            throw new MalformedFrameException(
                'Invalid header name: header name must not contain surrounding whitespace'
            );
        }
    }
}
