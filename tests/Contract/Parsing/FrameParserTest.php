<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Parsing;

use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for FrameParser behavior against deterministic fixtures.
 */
final class FrameParserTest extends TestCase
{
    private FrameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FrameParser();
    }

    // ---------------------------------------------------------------------------
    // Auth frames
    // ---------------------------------------------------------------------------

    public function test_auth_request_parses_to_single_frame(): void
    {
        $this->parser->feed(EslFixtureBuilder::authRequest());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('auth/request', $frames[0]->contentType());
        $this->assertFalse($frames[0]->hasBody());
    }

    public function test_auth_accepted_reply_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::authAccepted());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('command/reply', $frames[0]->contentType());
        $this->assertSame('+OK accepted', $frames[0]->replyText());
    }

    public function test_auth_rejected_reply_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::authRejected());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('command/reply', $frames[0]->contentType());
        $this->assertSame('-ERR invalid', $frames[0]->replyText());
    }

    // ---------------------------------------------------------------------------
    // Command reply frames
    // ---------------------------------------------------------------------------

    public function test_bgapi_accepted_reply_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::bgapiAccepted('7f4db0f2-b848-4b0a-b3cf-559bdca96b38'));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('command/reply', $frames[0]->contentType());
        $this->assertSame(
            '+OK Job-UUID: 7f4db0f2-b848-4b0a-b3cf-559bdca96b38',
            $frames[0]->replyText()
        );
    }

    public function test_command_reply_ok_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::commandReplyOk());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('+OK', $frames[0]->replyText());
    }

    public function test_command_reply_err_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::commandReplyErr('command not found'));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('-ERR command not found', $frames[0]->replyText());
    }

    // ---------------------------------------------------------------------------
    // API response
    // ---------------------------------------------------------------------------

    public function test_api_response_with_body_parses(): void
    {
        $body = "+OK\n";
        $this->parser->feed(EslFixtureBuilder::apiResponse($body));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('api/response', $frames[0]->contentType());
        $this->assertSame($body, $frames[0]->body);
        $this->assertSame(strlen($body), $frames[0]->contentLength());
    }

    public function test_api_response_body_bytes_are_exact(): void
    {
        $body = "UP 0 years, 0 days, 0 hours, 0 minutes, 3 seconds\n";
        $this->parser->feed(EslFixtureBuilder::apiResponse($body));
        $frames = $this->parser->drain();

        $this->assertSame($body, $frames[0]->body);
    }

    // ---------------------------------------------------------------------------
    // Event frames
    // ---------------------------------------------------------------------------

    public function test_channel_create_event_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::channelCreateEvent());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('text/event-plain', $frames[0]->contentType());
        $this->assertTrue($frames[0]->hasBody());
        $this->assertStringContainsString('CHANNEL_CREATE', $frames[0]->body);
    }

    public function test_background_job_event_parses(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        $this->parser->feed(EslFixtureBuilder::backgroundJobEvent($jobUuid));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('text/event-plain', $frames[0]->contentType());
        $this->assertStringContainsString('BACKGROUND_JOB', $frames[0]->body);
        $this->assertStringContainsString($jobUuid, $frames[0]->body);
    }

    public function test_hangup_event_parses(): void
    {
        $this->parser->feed(EslFixtureBuilder::hangupEvent());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertStringContainsString('CHANNEL_HANGUP', $frames[0]->body);
    }

    // ---------------------------------------------------------------------------
    // Multiple frames in one feed
    // ---------------------------------------------------------------------------

    public function test_multiple_frames_in_single_feed(): void
    {
        $combined = EslFixtureBuilder::authRequest()
            . EslFixtureBuilder::authAccepted();

        $this->parser->feed($combined);
        $frames = $this->parser->drain();

        $this->assertCount(2, $frames);
        $this->assertSame('auth/request', $frames[0]->contentType());
        $this->assertSame('command/reply', $frames[1]->contentType());
    }

    public function test_three_frames_in_sequence(): void
    {
        $bytes = EslFixtureBuilder::authRequest()
            . EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain');

        $this->parser->feed($bytes);
        $frames = $this->parser->drain();

        $this->assertCount(3, $frames);
    }

    // ---------------------------------------------------------------------------
    // Drain semantics
    // ---------------------------------------------------------------------------

    public function test_drain_clears_completed_frames(): void
    {
        $this->parser->feed(EslFixtureBuilder::authRequest());

        $first  = $this->parser->drain();
        $second = $this->parser->drain();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
    }

    public function test_drain_returns_empty_when_no_frames_available(): void
    {
        $frames = $this->parser->drain();

        $this->assertEmpty($frames);
    }

    // ---------------------------------------------------------------------------
    // Reset
    // ---------------------------------------------------------------------------

    public function test_reset_clears_all_state(): void
    {
        // Feed half of a frame
        $this->parser->feed("Content-Type: api/response\n");
        $this->parser->reset();

        // Feed a complete new frame
        $this->parser->feed(EslFixtureBuilder::authRequest());
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame('auth/request', $frames[0]->contentType());
    }

    public function test_reset_clears_buffered_byte_count(): void
    {
        $this->parser->feed("partial data without terminator");
        $this->assertGreaterThan(0, $this->parser->bufferedByteCount());

        $this->parser->reset();
        $this->assertSame(0, $this->parser->bufferedByteCount());
    }

    // ---------------------------------------------------------------------------
    // Buffered byte count
    // ---------------------------------------------------------------------------

    public function test_buffered_byte_count_decreases_after_frame_completed(): void
    {
        $partial = "Content-Type: auth/request\n"; // No terminator yet
        $this->parser->feed($partial);
        $this->assertSame(strlen($partial), $this->parser->bufferedByteCount());

        $this->parser->feed("\n"); // Terminate headers
        $this->parser->drain();
        $this->assertSame(0, $this->parser->bufferedByteCount());
    }
}
