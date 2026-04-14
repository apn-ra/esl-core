<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class LiveReplyFixtureTest extends TestCase
{
    private FrameParser $parser;
    private InboundMessageClassifier $classifier;
    private ReplyFactory $factory;

    protected function setUp(): void
    {
        $this->parser = new FrameParser();
        $this->classifier = new InboundMessageClassifier();
        $this->factory = new ReplyFactory();
    }

    public function test_live_auth_accepted_fixture_preserves_auth_reply_shape(): void
    {
        $reply = $this->replyFromFixture(
            'live/auth-accepted-command-reply.esl',
            InboundMessageCategory::AuthAccepted,
        );

        $this->assertInstanceOf(AuthAcceptedReply::class, $reply);
        $this->assertTrue($reply->isSuccess());
        $this->assertSame('+OK accepted', $reply->replyText());
    }

    public function test_live_api_status_fixture_remains_a_typed_api_reply_but_not_ok_prefixed(): void
    {
        $reply = $this->replyFromFixture(
            'live/api-status-response.esl',
            InboundMessageCategory::ApiResponse,
        );

        $this->assertInstanceOf(ApiReply::class, $reply);
        $this->assertFalse($reply->isSuccess());
        $this->assertStringStartsWith('UP 0 years', $reply->body());
        $this->assertStringContainsString('FreeSWITCH', $reply->body());
        $this->assertStringContainsString('is ready', $reply->body());
    }

    private function replyFromFixture(string $relativePath, InboundMessageCategory $expectedCategory): ReplyInterface
    {
        $this->parser->reset();
        $this->parser->feed(FixtureLoader::loadFrame($relativePath));
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);

        $classified = $this->classifier->classify($frames[0]);
        $this->assertSame($expectedCategory, $classified->category);

        return $this->factory->fromClassified($classified);
    }
}
