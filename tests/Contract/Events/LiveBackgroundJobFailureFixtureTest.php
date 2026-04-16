<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the curated live fixture for a BACKGROUND_JOB event carrying a
 * -ERR NO_ROUTE_DESTINATION failure body.
 *
 * Observed during live call-flow validation: FreeSWITCH accepted a bgapi
 * originate command and returned a loopback call attempt, but the route
 * lookup for apn-esl-core-events/apn-esl-core-smoke failed because the
 * test dialplan was not yet installed on the target PBX.
 *
 * Source: tools/smoke/captures/20260414T062140Z-call-flow-plain-012-full-frame-6fe70db4.esl
 * Promoted to: tests/Fixtures/live/events/background-job-no-route-destination-plain.esl
 *
 * This test validates the current package behavior honestly.
 * It intentionally uses lower-level parser/classifier/event-factory assembly
 * to pin fixture truth rather than to suggest the preferred downstream ingress
 * path.
 * It does not add new typed-event expectations.
 * Bridge/playback event expectations remain deferred pending a successful PBX rerun.
 */
final class LiveBackgroundJobFailureFixtureTest extends TestCase
{
    private const FIXTURE = 'live/events/background-job-no-route-destination-plain.esl';

    private const JOB_UUID     = '4786f47d-2b41-48e4-813b-380f1e9bf359';
    private const CORE_UUID    = '50cfb839-479a-4c7f-ab0b-5e3d0d4bf6be';
    private const SEQ          = '387897';

    private FrameParser $frameParser;
    private InboundMessageClassifier $classifier;
    private EventParser $eventParser;
    private EventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->frameParser  = new FrameParser();
        $this->classifier   = new InboundMessageClassifier();
        $this->eventParser  = new EventParser();
        $this->eventFactory = new EventFactory();
    }

    // ---------------------------------------------------------------------------
    // Wire-level: parse + classify
    // ---------------------------------------------------------------------------

    public function test_fixture_parses_as_exactly_one_frame(): void
    {
        $this->frameParser->feed(FixtureLoader::loadFrame(self::FIXTURE));
        $frames = $this->frameParser->drain();

        $this->assertCount(1, $frames);
    }

    public function test_fixture_classifies_as_event_message(): void
    {
        $this->frameParser->feed(FixtureLoader::loadFrame(self::FIXTURE));
        $frames = $this->frameParser->drain();

        $classified = $this->classifier->classify($frames[0]);

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
    }

    // ---------------------------------------------------------------------------
    // Event-level: normalized headers
    // ---------------------------------------------------------------------------

    public function test_event_name_is_background_job(): void
    {
        $event = $this->loadNormalized();

        $this->assertSame('BACKGROUND_JOB', $event->eventName());
    }

    public function test_event_sequence_matches_wire(): void
    {
        $event = $this->loadNormalized();

        $this->assertSame(self::SEQ, $event->eventSequence());
    }

    public function test_core_uuid_matches_wire(): void
    {
        $event = $this->loadNormalized();

        $this->assertSame(self::CORE_UUID, $event->coreUuid());
    }

    public function test_job_uuid_matches_wire(): void
    {
        $event = $this->loadNormalized();

        $this->assertSame(self::JOB_UUID, $event->jobUuid());
    }

    public function test_job_command_decodes_to_originate(): void
    {
        $event = $this->loadNormalized();

        $this->assertSame('originate', $event->jobCommand());
    }

    // ---------------------------------------------------------------------------
    // Typed event: BackgroundJobEvent
    // ---------------------------------------------------------------------------

    public function test_factory_produces_background_job_event(): void
    {
        $this->frameParser->feed(FixtureLoader::loadFrame(self::FIXTURE));
        $frames = $this->frameParser->drain();
        $event  = $this->eventFactory->fromFrame($frames[0]);

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
    }

    public function test_typed_event_is_not_success(): void
    {
        $event = $this->loadTyped();

        $this->assertFalse($event->isSuccess());
    }

    public function test_result_body_starts_with_err(): void
    {
        $event = $this->loadTyped();

        $this->assertStringStartsWith('-ERR', $event->result());
    }

    public function test_result_body_contains_no_route_destination(): void
    {
        $event = $this->loadTyped();

        $this->assertStringContainsString('NO_ROUTE_DESTINATION', $event->result());
    }

    public function test_result_body_is_preserved_from_wire(): void
    {
        // The event body is exactly the 26 bytes declared by the inner Content-Length header.
        // Verifying the raw body preserves the failure reason without any stripping.
        $event = $this->loadTyped();

        $this->assertSame("-ERR NO_ROUTE_DESTINATION\n", $event->result());
    }

    public function test_typed_event_job_uuid_matches_wire(): void
    {
        $event = $this->loadTyped();

        $this->assertSame(self::JOB_UUID, $event->jobUuid());
    }

    public function test_typed_event_core_uuid_matches_wire(): void
    {
        $event = $this->loadTyped();

        $this->assertSame(self::CORE_UUID, $event->coreUuid());
    }

    public function test_typed_event_job_command_matches_wire(): void
    {
        $event = $this->loadTyped();

        $this->assertSame('originate', $event->jobCommand());
    }

    public function test_failure_fixture_correlation_and_replay_metadata_preserve_job_identity(): void
    {
        $event = $this->loadTyped();
        $sessionId = ConnectionSessionId::fromString('56565656-5656-4565-8565-565656565656');
        $context = new CorrelationContext($sessionId);
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replay = ReplayEnvelopeFactory::withSession($sessionId)->fromEventEnvelope($envelope);

        $this->assertSame(self::JOB_UUID, $envelope->jobCorrelation()?->jobUuid());
        $this->assertSame(self::SEQ, $envelope->metadata()->protocolSequence());
        $this->assertSame(self::JOB_UUID, $replay->protocolFacts()['job-uuid'] ?? null);
        $this->assertSame(self::JOB_UUID, $replay->derivedMetadata()['job-correlation.job-uuid'] ?? null);
        $this->assertSame(self::SEQ, $replay->protocolSequence());
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function loadNormalized(): \Apntalk\EslCore\Events\NormalizedEvent
    {
        $this->frameParser->reset();
        $this->frameParser->feed(FixtureLoader::loadFrame(self::FIXTURE));
        $frames = $this->frameParser->drain();

        return $this->eventParser->parse($frames[0]);
    }

    private function loadTyped(): BackgroundJobEvent
    {
        $this->frameParser->reset();
        $this->frameParser->feed(FixtureLoader::loadFrame(self::FIXTURE));
        $frames = $this->frameParser->drain();
        $event  = $this->eventFactory->fromFrame($frames[0]);

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
        return $event;
    }
}
