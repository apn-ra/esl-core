<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Protocol;

use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Protocol\HeaderBag;
use PHPUnit\Framework\TestCase;

final class HeaderBagTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Parsing
    // ---------------------------------------------------------------------------

    public function test_empty_block_produces_empty_bag(): void
    {
        $bag = HeaderBag::fromHeaderBlock('');

        $this->assertTrue($bag->isEmpty());
        $this->assertSame(0, $bag->count());
    }

    public function test_single_header_parsed(): void
    {
        $bag = HeaderBag::fromHeaderBlock("Content-Type: auth/request");

        $this->assertSame('auth/request', $bag->get('Content-Type'));
    }

    public function test_multiple_headers_parsed(): void
    {
        $block = "Content-Type: command/reply\nReply-Text: +OK accepted";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('command/reply', $bag->get('Content-Type'));
        $this->assertSame('+OK accepted', $bag->get('Reply-Text'));
    }

    public function test_header_access_is_case_insensitive(): void
    {
        $bag = HeaderBag::fromHeaderBlock("Content-Type: auth/request");

        $this->assertSame('auth/request', $bag->get('content-type'));
        $this->assertSame('auth/request', $bag->get('CONTENT-TYPE'));
        $this->assertSame('auth/request', $bag->get('Content-Type'));
    }

    public function test_has_returns_true_for_present_header(): void
    {
        $bag = HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK");

        $this->assertTrue($bag->has('Content-Type'));
        $this->assertTrue($bag->has('reply-text'));
    }

    public function test_has_returns_false_for_absent_header(): void
    {
        $bag = HeaderBag::fromHeaderBlock("Content-Type: command/reply");

        $this->assertFalse($bag->has('Content-Length'));
        $this->assertFalse($bag->has('Reply-Text'));
    }

    public function test_get_returns_null_for_absent_header(): void
    {
        $bag = HeaderBag::fromHeaderBlock("Content-Type: auth/request");

        $this->assertNull($bag->get('Reply-Text'));
    }

    public function test_repeated_header_returns_all_values(): void
    {
        $block = "X-Custom: first\nX-Custom: second\nX-Custom: third";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('first', $bag->get('X-Custom'));
        $this->assertSame(['first', 'second', 'third'], $bag->all('X-Custom'));
    }

    public function test_crlf_line_endings_tolerated(): void
    {
        $block = "Content-Type: command/reply\r\nReply-Text: +OK\r\n";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('command/reply', $bag->get('Content-Type'));
        $this->assertSame('+OK', $bag->get('Reply-Text'));
    }

    public function test_empty_lines_in_block_are_ignored(): void
    {
        $block = "\nContent-Type: auth/request\n\n";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('auth/request', $bag->get('Content-Type'));
        $this->assertSame(1, $bag->count());
    }

    public function test_malformed_line_without_colon_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        HeaderBag::fromHeaderBlock("Content-Type auth/request");
    }

    public function test_empty_header_name_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        HeaderBag::fromHeaderBlock(": value");
    }

    public function test_header_name_with_surrounding_whitespace_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        HeaderBag::fromHeaderBlock("Content-Type : auth/request");
    }

    // ---------------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------------

    public function test_names_returns_lowercase_keys(): void
    {
        $block = "Content-Type: command/reply\nReply-Text: +OK";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $names = $bag->names();
        sort($names);

        $this->assertSame(['content-type', 'reply-text'], $names);
    }

    public function test_to_array_preserves_original_casing(): void
    {
        $block = "Content-Type: command/reply\nReply-Text: +OK";
        $bag   = HeaderBag::fromHeaderBlock($block);
        $arr   = $bag->toArray();

        $this->assertArrayHasKey('Content-Type', $arr);
        $this->assertArrayHasKey('Reply-Text', $arr);
        $this->assertSame('command/reply', $arr['Content-Type']);
    }

    public function test_to_flat_array_includes_repeated_headers(): void
    {
        $block = "X-Custom: a\nX-Custom: b";
        $bag   = HeaderBag::fromHeaderBlock($block);
        $flat  = $bag->toFlatArray();

        $this->assertCount(2, $flat);
        $this->assertSame('X-Custom', $flat[0]['name']);
        $this->assertSame('a', $flat[0]['value']);
        $this->assertSame('X-Custom', $flat[1]['name']);
        $this->assertSame('b', $flat[1]['value']);
    }

    public function test_count_counts_distinct_names_not_values(): void
    {
        $block = "X-A: 1\nX-B: 2\nX-A: 3"; // X-A appears twice
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame(2, $bag->count());
    }

    // ---------------------------------------------------------------------------
    // Immutability
    // ---------------------------------------------------------------------------

    public function test_with_returns_new_instance(): void
    {
        $bag     = HeaderBag::fromHeaderBlock("Content-Type: auth/request");
        $updated = $bag->with('Reply-Text', '+OK');

        $this->assertNull($bag->get('Reply-Text'));
        $this->assertSame('+OK', $updated->get('Reply-Text'));
        $this->assertSame('auth/request', $updated->get('Content-Type'));
    }

    // ---------------------------------------------------------------------------
    // Value preservation
    // ---------------------------------------------------------------------------

    public function test_header_value_with_colon_is_preserved(): void
    {
        // FreeSWITCH timestamps can contain colons in values
        $block = "Event-Date-GMT: Thu, 22 Dec 2016 15:30:00 GMT";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('Thu, 22 Dec 2016 15:30:00 GMT', $bag->get('Event-Date-GMT'));
    }

    public function test_url_encoded_values_are_not_decoded(): void
    {
        // HeaderBag stores raw values; decoding is the caller's responsibility
        $block = "Channel-Name: sofia/internal/1001%40192.168.1.100";
        $bag   = HeaderBag::fromHeaderBlock($block);

        $this->assertSame('sofia/internal/1001%40192.168.1.100', $bag->get('Channel-Name'));
    }
}
