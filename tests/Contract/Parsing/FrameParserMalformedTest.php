<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Parsing;

use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Parsing\FrameParser;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests verifying that the FrameParser fails predictably on malformed input.
 */
final class FrameParserMalformedTest extends TestCase
{
    private FrameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FrameParser();
    }

    public function test_malformed_header_line_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        // Header line with no colon separator
        $this->parser->feed("Content-Type auth/request\n\n");
    }

    public function test_invalid_content_length_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        $this->parser->feed("Content-Type: api/response\nContent-Length: not-a-number\n\n");
    }

    public function test_partial_frame_produces_no_frames(): void
    {
        // Feed only headers without terminator — should buffer, not throw
        $this->parser->feed("Content-Type: auth/request\n");
        $frames = $this->parser->drain();

        $this->assertEmpty($frames);
        $this->assertGreaterThan(0, $this->parser->bufferedByteCount());
    }

    public function test_body_partial_produces_no_frames(): void
    {
        // Headers complete but body truncated
        $this->parser->feed("Content-Type: api/response\nContent-Length: 100\n\n");
        $this->parser->feed("only 20 bytes of body ");

        $frames = $this->parser->drain();

        $this->assertEmpty($frames);
    }

    public function test_empty_feed_produces_no_frames(): void
    {
        $this->parser->feed('');
        $this->assertEmpty($this->parser->drain());
    }

    public function test_only_newlines_produces_no_frames(): void
    {
        // Double newline alone is a frame with empty headers and no body
        // This is a degenerate case — not malformed, but yields an empty-header frame
        $this->parser->feed("\n\n");
        $frames = $this->parser->drain();

        // An empty header block is valid (no headers, no body)
        $this->assertCount(1, $frames);
        $this->assertNull($frames[0]->contentType());
        $this->assertFalse($frames[0]->hasBody());
    }

    public function test_reset_after_parse_exception_allows_fresh_parse(): void
    {
        try {
            $this->parser->feed("bad line no colon\n\n");
        } catch (ParseException) {
            // expected
        }

        $this->parser->reset();
        $this->parser->feed("Content-Type: auth/request\n\n");
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
    }

    public function test_zero_content_length_produces_empty_body_frame(): void
    {
        $this->parser->feed("Content-Type: api/response\nContent-Length: 0\n\n");
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertFalse($frames[0]->hasBody());
    }
}
