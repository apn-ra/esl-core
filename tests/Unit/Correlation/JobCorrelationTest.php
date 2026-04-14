<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Correlation;

use Apntalk\EslCore\Correlation\JobCorrelation;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JobCorrelationTest extends TestCase
{
    private const JOB_UUID = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';

    // ---------------------------------------------------------------------------
    // fromString
    // ---------------------------------------------------------------------------

    public function test_from_string_stores_uuid(): void
    {
        $corr = JobCorrelation::fromString(self::JOB_UUID);

        $this->assertSame(self::JOB_UUID, $corr->jobUuid());
    }

    public function test_from_string_empty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JobCorrelation::fromString('');
    }

    // ---------------------------------------------------------------------------
    // fromBgapiReply
    // ---------------------------------------------------------------------------

    public function test_from_bgapi_reply_extracts_job_uuid(): void
    {
        $reply = $this->makeBgapiReply(self::JOB_UUID);
        $corr  = JobCorrelation::fromBgapiReply($reply);

        $this->assertNotNull($corr);
        $this->assertSame(self::JOB_UUID, $corr->jobUuid());
    }

    public function test_from_bgapi_reply_throws_for_unexpected_bgapi_reply_shape(): void
    {
        $this->expectException(UnexpectedReplyException::class);

        $parser = new FrameParser();
        $parser->feed("Content-Type: command/reply\nReply-Text: +OK\n\n");
        $frames = $parser->drain();
        BgapiAcceptedReply::fromFrame($frames[0]);
    }

    // ---------------------------------------------------------------------------
    // matches
    // ---------------------------------------------------------------------------

    public function test_matches_returns_true_for_same_uuid(): void
    {
        $corr = JobCorrelation::fromString(self::JOB_UUID);

        $this->assertTrue($corr->matches(self::JOB_UUID));
    }

    public function test_matches_returns_false_for_different_uuid(): void
    {
        $corr = JobCorrelation::fromString(self::JOB_UUID);

        $this->assertFalse($corr->matches('other-uuid'));
    }

    // ---------------------------------------------------------------------------
    // equals
    // ---------------------------------------------------------------------------

    public function test_equal_correlations(): void
    {
        $a = JobCorrelation::fromString(self::JOB_UUID);
        $b = JobCorrelation::fromString(self::JOB_UUID);

        $this->assertTrue($a->equals($b));
    }

    public function test_unequal_correlations(): void
    {
        $a = JobCorrelation::fromString(self::JOB_UUID);
        $b = JobCorrelation::fromString('different-uuid');

        $this->assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeBgapiReply(string $jobUuid): BgapiAcceptedReply
    {
        $parser     = new FrameParser();
        $classifier = new InboundMessageClassifier();
        $factory    = new ReplyFactory();

        $parser->feed(EslFixtureBuilder::bgapiAccepted($jobUuid));
        $frames     = $parser->drain();
        $classified = $classifier->classify($frames[0]);
        $reply      = $factory->fromClassified($classified);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);
        return $reply;
    }
}
