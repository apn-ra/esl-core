<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Classification;

use Apntalk\EslCore\Contracts\ClassifiedMessageInterface;
use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\MessageType;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-backed tests for the lower-level classifier seam.
 *
 * This class exists to pin truthful classification behavior for advanced
 * composition and internal coverage, not to suggest the preferred upper-layer
 * ingress path.
 */
final class InboundMessageClassifierTest extends TestCase
{
    private InboundMessageClassifier $classifier;
    private FrameParser $parser;

    protected function setUp(): void
    {
        $this->classifier = new InboundMessageClassifier();
        $this->parser     = new FrameParser();
    }

    private function classify(string $fixture): ClassifiedInboundMessage
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

        $this->assertInstanceOf(ClassifiedMessageInterface::class, $classified);
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
        // The classifier cannot distinguish auth -ERR from command -ERR,
        // so the public classified-message contract exposes only CommandError here.
        $classified = $this->classify(EslFixtureBuilder::authRejected());

        $this->assertSame(InboundMessageCategory::CommandError, $classified->category);
        $this->assertTrue($classified->isCommandError());
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

        $this->assertSame(MessageType::AuthRequest, $classified->messageType);
    }

    public function test_original_frame_preserved_on_classified_message(): void
    {
        $raw        = EslFixtureBuilder::authAccepted();
        $classified = $this->classify($raw);

        $this->assertSame('command/reply', $classified->frame->contentType());
        $this->assertSame('+OK accepted', $classified->frame->replyText());
        $this->assertSame($classified->frame, $classified->frame());
    }

    public function test_classified_message_contract_can_be_used_without_internal_properties(): void
    {
        $classified = $this->classify(EslFixtureBuilder::apiResponse("+OK\n"));

        $this->assertInstanceOf(ClassifiedMessageInterface::class, $classified);
        $this->assertTrue($classified->isApiResponse());
        $this->assertFalse($classified->isEvent());
        $this->assertSame('api/response', $classified->frame()->contentType());
    }

    public function test_downstream_classifier_can_implement_public_interface_without_internal_carrier(): void
    {
        $classifier = new class implements InboundMessageClassifierInterface {
            public function classify(Frame $frame): ClassifiedMessageInterface
            {
                return new class ($frame) implements ClassifiedMessageInterface {
                    public function __construct(
                        private readonly Frame $frame,
                    ) {}

                    public function frame(): Frame
                    {
                        return $this->frame;
                    }

                    public function isAuthRequest(): bool
                    {
                        return false;
                    }

                    public function isAuthAccepted(): bool
                    {
                        return false;
                    }

                    public function isBgapiAccepted(): bool
                    {
                        return false;
                    }

                    public function isCommandAccepted(): bool
                    {
                        return false;
                    }

                    public function isCommandError(): bool
                    {
                        return false;
                    }

                    public function isApiResponse(): bool
                    {
                        return false;
                    }

                    public function isEvent(): bool
                    {
                        return false;
                    }

                    public function isDisconnectNotice(): bool
                    {
                        return false;
                    }

                    public function isUnknown(): bool
                    {
                        return true;
                    }
                };
            }
        };

        $classified = $classifier->classify($this->parsedFrame(EslFixtureBuilder::frame([
            'Content-Type' => 'application/x-downstream',
        ])));

        $this->assertInstanceOf(ClassifiedMessageInterface::class, $classified);
        $this->assertTrue($classified->isUnknown());
        $this->assertSame('application/x-downstream', $classified->frame()->contentType());
    }

    public function test_existing_classifier_implementation_returning_internal_carrier_remains_compatible(): void
    {
        $classifier = new class implements InboundMessageClassifierInterface {
            public function classify(Frame $frame): ClassifiedInboundMessage
            {
                return new ClassifiedInboundMessage(
                    InboundMessageCategory::CommandAccepted,
                    $frame,
                    MessageType::CommandReply,
                );
            }
        };

        $classified = $classifier->classify($this->parsedFrame(EslFixtureBuilder::commandReplyOk()));

        $this->assertInstanceOf(ClassifiedMessageInterface::class, $classified);
        $this->assertTrue($classified->isCommandAccepted());
        $this->assertSame('+OK', $classified->frame()->replyText());
    }

    private function parsedFrame(string $fixture): Frame
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();
        $this->assertCount(1, $frames);

        return $frames[0];
    }
}
