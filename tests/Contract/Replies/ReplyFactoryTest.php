<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Replies;

use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\CommandReply;
use Apntalk\EslCore\Replies\ErrorReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replies\UnknownReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

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
}
