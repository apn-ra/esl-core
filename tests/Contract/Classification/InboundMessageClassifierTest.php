<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Classification;

use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class InboundMessageClassifierTest extends TestCase
{
    private InboundMessageClassifier $classifier;
    private FrameParser $parser;

    protected function setUp(): void
    {
        $this->classifier = new InboundMessageClassifier();
        $this->parser     = new FrameParser();
    }

    private function classify(string $fixture): \Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();
        $this->assertCount(1, $frames, 'Expected exactly one frame from fixture');
        return $this->classifier->classify($frames[0]);
    }

    // ---------------------------------------------------------------------------
    // Auth flow
    // ---------------------------------------------------------------------------

    public function test_auth_request_classified_as_server_auth_request(): void
    {
        $classified = $this->classify(EslFixtureBuilder::authRequest());

        $this->assertSame(InboundMessageCategory::ServerAuthRequest, $classified->category);
        $this->assertTrue($classified->isAuthRequest());
    }

    public function test_auth_accepted_reply_classified_as_auth_accepted(): void
    {
        $classified = $this->classify(EslFixtureBuilder::authAccepted());

        $this->assertSame(InboundMessageCategory::AuthAccepted, $classified->category);
        $this->assertTrue($classified->isAuthAccepted());
    }

    public function test_auth_rejected_classified_as_command_error(): void
    {
        // The classifier cannot distinguish auth -ERR from command -ERR;
        // it returns CommandError. Session-state layer refines this.
        $classified = $this->classify(EslFixtureBuilder::authRejected());

        $this->assertSame(InboundMessageCategory::CommandError, $classified->category);
    }

    // ---------------------------------------------------------------------------
    // Command replies
    // ---------------------------------------------------------------------------

    public function test_bgapi_accepted_classified_correctly(): void
    {
        $classified = $this->classify(EslFixtureBuilder::bgapiAccepted());

        $this->assertSame(InboundMessageCategory::BgapiAccepted, $classified->category);
        $this->assertTrue($classified->isBgapiAccepted());
    }

    public function test_ok_command_reply_classified_as_command_accepted(): void
    {
        $classified = $this->classify(EslFixtureBuilder::commandReplyOk());

        $this->assertSame(InboundMessageCategory::CommandAccepted, $classified->category);
        $this->assertTrue($classified->isCommandAccepted());
    }

    public function test_ok_command_reply_with_description(): void
    {
        $classified = $this->classify(
            EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain')
        );

        $this->assertSame(InboundMessageCategory::CommandAccepted, $classified->category);
    }

    public function test_err_command_reply_classified_as_command_error(): void
    {
        $classified = $this->classify(EslFixtureBuilder::commandReplyErr('command not found'));

        $this->assertSame(InboundMessageCategory::CommandError, $classified->category);
        $this->assertTrue($classified->isCommandError());
    }

    // ---------------------------------------------------------------------------
    // API response
    // ---------------------------------------------------------------------------

    public function test_api_response_classified(): void
    {
        $classified = $this->classify(EslFixtureBuilder::apiResponse("+OK\n"));

        $this->assertSame(InboundMessageCategory::ApiResponse, $classified->category);
        $this->assertTrue($classified->isApiResponse());
    }

    // ---------------------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------------------

    public function test_channel_create_event_classified_as_event(): void
    {
        $classified = $this->classify(EslFixtureBuilder::channelCreateEvent());

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
        $this->assertTrue($classified->isEvent());
    }

    public function test_background_job_event_classified_as_event(): void
    {
        $classified = $this->classify(EslFixtureBuilder::backgroundJobEvent());

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
    }

    public function test_hangup_event_classified_as_event(): void
    {
        $classified = $this->classify(EslFixtureBuilder::hangupEvent());

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
    }

    // ---------------------------------------------------------------------------
    // Disconnect notice
    // ---------------------------------------------------------------------------

    public function test_disconnect_notice_classified(): void
    {
        $classified = $this->classify(EslFixtureBuilder::disconnectNotice());

        $this->assertSame(InboundMessageCategory::DisconnectNotice, $classified->category);
        $this->assertTrue($classified->isDisconnectNotice());
    }

    // ---------------------------------------------------------------------------
    // Unknown / degrade safely
    // ---------------------------------------------------------------------------

    public function test_unknown_content_type_degrades_to_unknown(): void
    {
        $fixture    = EslFixtureBuilder::frame(['Content-Type' => 'application/x-unknown']);
        $classified = $this->classify($fixture);

        $this->assertSame(InboundMessageCategory::Unknown, $classified->category);
        $this->assertTrue($classified->isUnknown());
    }

    public function test_missing_content_type_degrades_to_unknown(): void
    {
        $fixture    = EslFixtureBuilder::frame(['X-Custom' => 'value']);
        $classified = $this->classify($fixture);

        $this->assertSame(InboundMessageCategory::Unknown, $classified->category);
    }

    // ---------------------------------------------------------------------------
    // Message type preserved
    // ---------------------------------------------------------------------------

    public function test_message_type_preserved_on_classified_message(): void
    {
        $classified = $this->classify(EslFixtureBuilder::authRequest());

        $this->assertSame(\Apntalk\EslCore\Protocol\MessageType::AuthRequest, $classified->messageType);
    }

    public function test_original_frame_preserved_on_classified_message(): void
    {
        $raw        = EslFixtureBuilder::authAccepted();
        $classified = $this->classify($raw);

        $this->assertSame('command/reply', $classified->frame->contentType());
        $this->assertSame('+OK accepted', $classified->frame->replyText());
    }
}
