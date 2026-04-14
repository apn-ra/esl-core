<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Correlation;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConnectionSessionIdTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Generation
    // ---------------------------------------------------------------------------

    public function test_generate_returns_non_empty_string(): void
    {
        $id = ConnectionSessionId::generate();

        $this->assertNotSame('', $id->toString());
    }

    public function test_generate_produces_uuid_v4_format(): void
    {
        $id = ConnectionSessionId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString(),
        );
    }

    public function test_two_generated_ids_are_distinct(): void
    {
        $a = ConnectionSessionId::generate();
        $b = ConnectionSessionId::generate();

        $this->assertFalse($a->equals($b));
        $this->assertNotSame($a->toString(), $b->toString());
    }

    // ---------------------------------------------------------------------------
    // fromString
    // ---------------------------------------------------------------------------

    public function test_from_string_round_trips_value(): void
    {
        $raw = 'my-custom-session-id';
        $id  = ConnectionSessionId::fromString($raw);

        $this->assertSame($raw, $id->toString());
    }

    public function test_from_string_empty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionSessionId::fromString('');
    }

    // ---------------------------------------------------------------------------
    // Equality
    // ---------------------------------------------------------------------------

    public function test_equal_ids_from_string(): void
    {
        $a = ConnectionSessionId::fromString('session-abc');
        $b = ConnectionSessionId::fromString('session-abc');

        $this->assertTrue($a->equals($b));
    }

    public function test_unequal_ids_from_string(): void
    {
        $a = ConnectionSessionId::fromString('session-abc');
        $b = ConnectionSessionId::fromString('session-xyz');

        $this->assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------------------
    // Stringable
    // ---------------------------------------------------------------------------

    public function test_to_string_magic_matches_to_string(): void
    {
        $id = ConnectionSessionId::fromString('test-id');

        $this->assertSame($id->toString(), (string) $id);
    }
}
