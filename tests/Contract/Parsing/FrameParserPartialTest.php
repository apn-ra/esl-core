<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Parsing;

use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for FrameParser partial-read behavior.
 *
 * These tests verify that the parser handles bytes arriving in arbitrary
 * chunk sizes: split across header boundaries, body boundaries, and
 * mid-body positions.
 */
final class FrameParserPartialTest extends TestCase
{
    private FrameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FrameParser();
    }

    public function test_header_split_across_two_feeds(): void
    {
        $full = EslFixtureBuilder::authRequest(); // "Content-Type: auth/request\n\n"

        $mid = (int) (strlen($full) / 2);
        $this->parser->feed(substr($full, 0, $mid));
        $this->assertEmpty($this->parser->drain());

        $this->parser->feed(substr($full, $mid));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('auth/request', $frames[0]->contentType());
    }

    public function test_body_split_across_feeds(): void
    {
        $body  = str_repeat('x', 100);
        $full  = EslFixtureBuilder::apiResponse($body);

        // Feed up through headers
        $headerEnd = strpos($full, "\n\n") + 2;
        $this->parser->feed(substr($full, 0, $headerEnd));
        $this->assertEmpty($this->parser->drain());

        // Feed half body
        $halfBody = $headerEnd + 50;
        $this->parser->feed(substr($full, $headerEnd, 50));
        $this->assertEmpty($this->parser->drain());

        // Feed remainder
        $this->parser->feed(substr($full, $halfBody));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame($body, $frames[0]->body);
    }

    public function test_one_byte_at_a_time(): void
    {
        $full = EslFixtureBuilder::authAccepted();

        for ($i = 0; $i < strlen($full) - 1; $i++) {
            $this->parser->feed($full[$i]);
            $this->assertEmpty($this->parser->drain(), "Expected no frames after byte {$i}");
        }

        $this->parser->feed($full[strlen($full) - 1]);
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('+OK accepted', $frames[0]->replyText());
    }

    public function test_body_delivered_one_byte_at_a_time(): void
    {
        $body = "result data\n";
        $full = EslFixtureBuilder::apiResponse($body);

        foreach (str_split($full) as $i => $byte) {
            $this->parser->feed($byte);
        }

        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame($body, $frames[0]->body);
    }

    public function test_two_frames_split_at_boundary(): void
    {
        $frame1 = EslFixtureBuilder::authRequest();
        $frame2 = EslFixtureBuilder::authAccepted();
        $full   = $frame1 . $frame2;

        // Split exactly at frame boundary
        $this->parser->feed(substr($full, 0, strlen($frame1)));
        $first = $this->parser->drain();

        $this->parser->feed(substr($full, strlen($frame1)));
        $second = $this->parser->drain();

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame('auth/request', $first[0]->contentType());
        $this->assertSame('command/reply', $second[0]->contentType());
    }

    public function test_two_frames_split_inside_second_frame_headers(): void
    {
        $frame1    = EslFixtureBuilder::authRequest();
        $frame2raw = "Content-Type: command/reply\nReply-Text: +OK accepted\n\n";

        // Feed first frame plus part of second
        $splitAt = strlen($frame1) + 10;
        $this->parser->feed(substr($frame1 . $frame2raw, 0, $splitAt));
        $partial = $this->parser->drain();

        $this->parser->feed(substr($frame1 . $frame2raw, $splitAt));
        $remaining = $this->parser->drain();

        $this->assertCount(1, $partial);
        $this->assertCount(1, $remaining);
    }

    public function test_event_frame_split_across_body_boundary(): void
    {
        $full = EslFixtureBuilder::channelCreateEvent();

        // Split in the middle of the body
        $headerEnd = strpos($full, "\n\n") + 2;
        $bodyLen   = strlen($full) - $headerEnd;
        $splitAt   = $headerEnd + (int) ($bodyLen / 2);

        $this->parser->feed(substr($full, 0, $splitAt));
        $this->assertEmpty($this->parser->drain());

        $this->parser->feed(substr($full, $splitAt));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('text/event-plain', $frames[0]->contentType());
    }

    public function test_large_body_reassembled_from_small_chunks(): void
    {
        $body  = str_repeat("abcdefghij", 500); // 5000 bytes
        $full  = EslFixtureBuilder::apiResponse($body);
        $chunk = 64;

        for ($offset = 0; $offset < strlen($full); $offset += $chunk) {
            $this->parser->feed(substr($full, $offset, $chunk));
        }

        $frames = $this->parser->drain();
        $this->assertCount(1, $frames);
        $this->assertSame($body, $frames[0]->body);
    }

    public function test_digit_only_content_length_keeps_buffering_until_declared_body_bytes_arrive_without_parser_cap(): void
    {
        $body = str_repeat('x', 4096);
        $full = EslFixtureBuilder::apiResponse($body);
        $headerEnd = strpos($full, "\n\n") + 2;

        $this->parser->feed(substr($full, 0, $headerEnd));
        $this->assertEmpty($this->parser->drain());

        $partialBody = 512;
        $this->parser->feed(substr($full, $headerEnd, $partialBody));
        $this->assertEmpty($this->parser->drain());
        $this->assertSame($partialBody, $this->parser->bufferedByteCount());

        $this->parser->feed(substr($full, $headerEnd + $partialBody));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame($body, $frames[0]->body);
    }

    public function test_three_coalesced_frames_drain_together_after_single_feed(): void
    {
        $full = EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::bgapiAccepted()
            . EslFixtureBuilder::backgroundJobEvent();

        $this->parser->feed($full);
        $frames = $this->parser->drain();

        $this->assertCount(3, $frames);
        $this->assertSame('command/reply', $frames[0]->contentType());
        $this->assertSame('+OK accepted', $frames[0]->replyText());
        $this->assertSame('command/reply', $frames[1]->contentType());
        $this->assertSame('text/event-plain', $frames[2]->contentType());
    }

    public function test_event_body_can_arrive_after_headers_and_inner_event_headers(): void
    {
        $result = "+OK delayed-body\n";
        $full = EslFixtureBuilder::backgroundJobEvent(jobResult: $result);
        $outerHeaderEnd = strpos($full, "\n\n");
        $eventBodyStart = strrpos($full, "\n\n") + 2;

        $this->assertNotFalse($outerHeaderEnd);
        $this->assertNotFalse($eventBodyStart);

        $this->parser->feed(substr($full, 0, $eventBodyStart));
        $this->assertEmpty($this->parser->drain());

        $this->parser->feed(substr($full, $eventBodyStart));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertStringStartsWith('Event-Name: BACKGROUND_JOB', $frames[0]->body);
        $this->assertSame($result, substr($frames[0]->body, -strlen($result)));
    }
}
