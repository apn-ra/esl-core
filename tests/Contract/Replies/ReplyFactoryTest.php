<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Replies;

use Apntalk\EslCore\Contracts\ClassifiedMessageInterface;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\CommandReply;
use Apntalk\EslCore\Replies\ErrorReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replies\UnknownReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Contract coverage for the lower-level typed reply bridge.
 *
 * ReplyFactory remains public, but this test class intentionally exercises the
 * advanced frame/classifier-owned composition path instead of the preferred
 * InboundPipeline ingress facade.
 */
final class ReplyFactoryTest extends TestCase
{
    private FrameParser $parser;
    private InboundMessageClassifier $classifier;
    private ReplyFactory $factory;

    protected function setUp(): void
    {
        $this->parser     = new FrameParser();
        $this->classifier = new InboundMessageClassifier();
        $this->factory    = new ReplyFactory();
    }

    private function reply(string $fixture): \Apntalk\EslCore\Contracts\ReplyInterface
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();
        $this->assertCount(1, $frames);
        $classified = $this->classifier->classify($frames[0]);
        return $this->factory->fromClassified($classified);
    }

    // ---------------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------------

    public function test_auth_accepted_produces_auth_accepted_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::authAccepted());

        $this->assertInstanceOf(AuthAcceptedReply::class, $reply);
        $this->assertTrue($reply->isSuccess());
    }

    public function test_auth_accepted_reply_text_is_correct(): void
    {
        /** @var AuthAcceptedReply $reply */
        $reply = $this->reply(EslFixtureBuilder::authAccepted());

        $this->assertSame('+OK accepted', $reply->replyText());
    }

    // ---------------------------------------------------------------------------
    // Bgapi
    // ---------------------------------------------------------------------------

    public function test_bgapi_accepted_produces_bgapi_accepted_reply(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        $reply   = $this->reply(EslFixtureBuilder::bgapiAccepted($jobUuid));

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);
        $this->assertTrue($reply->isSuccess());
    }

    public function test_bgapi_accepted_reply_extracts_job_uuid(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        /** @var BgapiAcceptedReply $reply */
        $reply = $this->reply(EslFixtureBuilder::bgapiAccepted($jobUuid));

        $this->assertSame($jobUuid, $reply->jobUuid());
    }

    // ---------------------------------------------------------------------------
    // Command replies
    // ---------------------------------------------------------------------------

    public function test_ok_reply_produces_command_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::commandReplyOk());

        $this->assertInstanceOf(CommandReply::class, $reply);
        $this->assertTrue($reply->isSuccess());
    }

    public function test_ok_reply_with_description_produces_command_reply(): void
    {
        $reply = $this->reply(
            EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain')
        );

        $this->assertInstanceOf(CommandReply::class, $reply);
    }

    public function test_command_reply_message_strips_ok_prefix(): void
    {
        /** @var CommandReply $reply */
        $reply = $this->reply(
            EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain')
        );

        $this->assertSame('event listener enabled plain', $reply->message());
    }

    public function test_plain_ok_produces_empty_message(): void
    {
        /** @var CommandReply $reply */
        $reply = $this->reply(EslFixtureBuilder::commandReplyOk('+OK'));

        $this->assertSame('', $reply->message());
    }

    // ---------------------------------------------------------------------------
    // Error replies
    // ---------------------------------------------------------------------------

    public function test_err_reply_produces_error_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::commandReplyErr('command not found'));

        $this->assertInstanceOf(ErrorReply::class, $reply);
        $this->assertFalse($reply->isSuccess());
    }

    public function test_error_reply_extracts_reason(): void
    {
        /** @var ErrorReply $reply */
        $reply = $this->reply(EslFixtureBuilder::commandReplyErr('command not found'));

        $this->assertSame('command not found', $reply->reason());
    }

    public function test_auth_rejected_produces_error_reply(): void
    {
        // Auth rejections (-ERR invalid) classify as CommandError at this layer
        $reply = $this->reply(EslFixtureBuilder::authRejected());

        $this->assertInstanceOf(ErrorReply::class, $reply);
        $this->assertFalse($reply->isSuccess());
    }

    // ---------------------------------------------------------------------------
    // API response
    // ---------------------------------------------------------------------------

    public function test_api_response_produces_api_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::apiResponse("+OK\n"));

        $this->assertInstanceOf(ApiReply::class, $reply);
    }

    public function test_api_reply_body_is_correct(): void
    {
        $body = "+OK some output\n";
        /** @var ApiReply $reply */
        $reply = $this->reply(EslFixtureBuilder::apiResponse($body));

        $this->assertSame($body, $reply->body());
    }

    public function test_api_reply_is_success_for_ok_prefix(): void
    {
        /** @var ApiReply $reply */
        $reply = $this->reply(EslFixtureBuilder::apiResponse("+OK\n"));

        $this->assertTrue($reply->isSuccess());
    }

    public function test_api_reply_is_failure_for_err_prefix(): void
    {
        /** @var ApiReply $reply */
        $reply = $this->reply(EslFixtureBuilder::apiResponse("-ERR no such command\n"));

        $this->assertFalse($reply->isSuccess());
    }

    public function test_api_reply_trimmed_body_removes_trailing_newline(): void
    {
        /** @var ApiReply $reply */
        $reply = $this->reply(EslFixtureBuilder::apiResponse("+OK\n"));

        $this->assertSame('+OK', $reply->trimmedBody());
    }

    // ---------------------------------------------------------------------------
    // Unknown / degrade safely
    // ---------------------------------------------------------------------------

    public function test_unknown_content_type_produces_unknown_reply(): void
    {
        $fixture = EslFixtureBuilder::frame(['Content-Type' => 'text/disconnect-notice']);
        $reply   = $this->reply($fixture);

        // Disconnect notices are classified as DisconnectNotice, not a reply category
        // The factory should degrade to UnknownReply for non-reply messages
        $this->assertInstanceOf(UnknownReply::class, $reply);
        $this->assertFalse($reply->isSuccess());
    }

    public function test_unknown_reply_preserves_content_type(): void
    {
        $fixture = EslFixtureBuilder::frame(['Content-Type' => 'text/disconnect-notice']);
        /** @var UnknownReply $reply */
        $reply = $this->reply($fixture);

        $this->assertSame('text/disconnect-notice', $reply->contentType());
    }

    // ---------------------------------------------------------------------------
    // Additive frame-owned helper path
    // ---------------------------------------------------------------------------

    public function test_from_frame_produces_same_typed_reply_as_from_classified_for_auth_accept(): void
    {
        $fromFrame = $this->replyFromFrame(EslFixtureBuilder::authAccepted());
        $fromClassified = $this->reply(EslFixtureBuilder::authAccepted());

        $this->assertInstanceOf(AuthAcceptedReply::class, $fromFrame);
        $this->assertSame($fromClassified::class, $fromFrame::class);
        $this->assertSame($fromClassified->frame()->replyText(), $fromFrame->frame()->replyText());
    }

    public function test_from_frame_produces_bgapi_accepted_reply_without_exposing_classified_message(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        $reply = $this->replyFromFrame(EslFixtureBuilder::bgapiAccepted($jobUuid));

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);
        $this->assertSame($jobUuid, $reply->jobUuid());
    }

    public function test_from_frame_produces_error_reply_for_command_error(): void
    {
        $reply = $this->replyFromFrame(EslFixtureBuilder::commandReplyErr('command not found'));

        $this->assertInstanceOf(ErrorReply::class, $reply);
        $this->assertSame('command not found', $reply->reason());
    }

    public function test_from_frame_produces_api_reply(): void
    {
        $reply = $this->replyFromFrame(EslFixtureBuilder::apiResponse("+OK status\n"));

        $this->assertInstanceOf(ApiReply::class, $reply);
        $this->assertSame("+OK status\n", $reply->body());
    }

    public function test_from_frame_degrades_disconnect_notice_to_unknown_reply(): void
    {
        $reply = $this->replyFromFrame(
            EslFixtureBuilder::frame(['Content-Type' => 'text/disconnect-notice'])
        );

        $this->assertInstanceOf(UnknownReply::class, $reply);
        $this->assertSame('text/disconnect-notice', $reply->contentType());
    }

    public function test_from_classification_produces_same_typed_reply_as_from_classified(): void
    {
        $this->parser->reset();
        $this->parser->feed(EslFixtureBuilder::bgapiAccepted());
        $frames = $this->parser->drain();
        $this->assertCount(1, $frames);

        $classified = $this->classifier->classify($frames[0]);
        $fromClassification = $this->factory->fromClassification($classified);
        $fromClassified = $this->factory->fromClassified($classified);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $fromClassification);
        $this->assertSame($fromClassified::class, $fromClassification::class);
        $this->assertSame($fromClassified->frame()->replyText(), $fromClassification->frame()->replyText());
    }

    public function test_from_classification_accepts_public_classified_message_contract_without_internal_carrier(): void
    {
        $classified = new class ($this->frame(EslFixtureBuilder::apiResponse("+OK status\n"))) implements ClassifiedMessageInterface {
            public function __construct(private readonly Frame $frame) {}

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

            public function isAuthRejected(): bool
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
                return true;
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
                return false;
            }
        };

        $reply = $this->factory->fromClassification($classified);

        $this->assertInstanceOf(ApiReply::class, $reply);
        $this->assertSame("+OK status\n", $reply->body());
    }

    // ---------------------------------------------------------------------------
    // Frame preserved on all replies
    // ---------------------------------------------------------------------------

    public function test_frame_is_preserved_on_auth_accepted_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::authAccepted());

        $this->assertSame('command/reply', $reply->frame()->contentType());
    }

    public function test_frame_is_preserved_on_error_reply(): void
    {
        $reply = $this->reply(EslFixtureBuilder::commandReplyErr('test'));

        $this->assertSame('command/reply', $reply->frame()->contentType());
    }

    private function replyFromFrame(string $fixture): \Apntalk\EslCore\Contracts\ReplyInterface
    {
        return $this->factory->fromFrame($this->frame($fixture), $this->classifier);
    }

    private function frame(string $fixture): Frame
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();
        $this->assertCount(1, $frames);

        return $frames[0];
    }
}
