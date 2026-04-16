<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Inbound;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\EventFormat;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Commands\FilterCommand;
use Apntalk\EslCore\Commands\NoEventsCommand;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\CommandReply;
use Apntalk\EslCore\Replies\ErrorReply;
use Apntalk\EslCore\Serialization\CommandSerializer;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

/**
 * Pins the public-facade connect-and-subscribe path used by higher layers.
 *
 * These tests verify both the outbound (command serialization → transport write)
 * and inbound (transport read → InboundPipeline decode) sides of the session
 * setup sequence, without reaching into provisional internal types.
 */
final class ConnectSubscribeTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Outbound: command wire bytes reach the transport correctly
    // ---------------------------------------------------------------------------

    public function test_auth_command_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write((new AuthCommand('ClueCon'))->serialize());

        $this->assertSame("auth ClueCon\n\n", $transport->drainOutbound());
    }

    public function test_event_subscription_all_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write(EventSubscriptionCommand::all()->serialize());

        $this->assertSame("event plain all\n\n", $transport->drainOutbound());
    }

    public function test_event_subscription_json_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write(EventSubscriptionCommand::all(EventFormat::Json)->serialize());

        $this->assertSame("event json all\n\n", $transport->drainOutbound());
    }

    public function test_event_subscription_named_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write(
            EventSubscriptionCommand::forNames(['BACKGROUND_JOB', 'CHANNEL_HANGUP'])->serialize()
        );

        $this->assertSame("event plain BACKGROUND_JOB CHANNEL_HANGUP\n\n", $transport->drainOutbound());
    }

    public function test_filter_command_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write(FilterCommand::add('Event-Name', 'CHANNEL_CREATE')->serialize());

        $this->assertSame("filter Event-Name CHANNEL_CREATE\n\n", $transport->drainOutbound());
    }

    public function test_noevents_command_writes_expected_wire_bytes_to_transport(): void
    {
        $transport = new InMemoryTransport();

        $transport->write((new NoEventsCommand())->serialize());

        $this->assertSame("noevents\n\n", $transport->drainOutbound());
    }

    // ---------------------------------------------------------------------------
    // CommandSerializer facade: same output as direct serialize()
    // ---------------------------------------------------------------------------

    public function test_command_serializer_facade_produces_same_bytes_as_direct_serialize(): void
    {
        $serializer = new CommandSerializer();

        $this->assertSame(
            (new AuthCommand('ClueCon'))->serialize(),
            $serializer->serialize(new AuthCommand('ClueCon'))
        );
        $this->assertSame(
            EventSubscriptionCommand::all()->serialize(),
            $serializer->serialize(EventSubscriptionCommand::all())
        );
        $this->assertSame(
            FilterCommand::add('Event-Name', 'CHANNEL_CREATE')->serialize(),
            $serializer->serialize(FilterCommand::add('Event-Name', 'CHANNEL_CREATE'))
        );
    }

    // ---------------------------------------------------------------------------
    // Inbound: individual session-setup frames decode correctly through pipeline
    // ---------------------------------------------------------------------------

    public function test_auth_request_frame_decodes_to_server_auth_request_through_pipeline(): void
    {
        $pipeline = InboundPipeline::withDefaults();

        $messages = $pipeline->decode(EslFixtureBuilder::authRequest());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::ServerAuthRequest, $messages[0]->type());
        $this->assertTrue($messages[0]->isServerAuthRequest());
    }

    public function test_auth_accepted_frame_decodes_to_typed_reply_through_pipeline(): void
    {
        $pipeline = InboundPipeline::withDefaults();

        $messages = $pipeline->decode(EslFixtureBuilder::authAccepted());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(AuthAcceptedReply::class, $messages[0]->reply());
        $this->assertTrue($messages[0]->reply()?->isSuccess());
    }

    public function test_subscription_accepted_frame_decodes_to_command_reply_through_pipeline(): void
    {
        $pipeline = InboundPipeline::withDefaults();

        // FreeSWITCH acknowledges event subscriptions with +OK Events Enabled
        $messages = $pipeline->decode(EslFixtureBuilder::commandReplyOk('+OK Events Enabled'));

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(CommandReply::class, $messages[0]->reply());
        $this->assertTrue($messages[0]->reply()?->isSuccess());
        $this->assertSame('Events Enabled', $messages[0]->reply()?->message());
    }

    public function test_auth_rejection_decodes_to_error_reply_through_pipeline(): void
    {
        $pipeline = InboundPipeline::withDefaults();

        $messages = $pipeline->decode(EslFixtureBuilder::authRejected());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(ErrorReply::class, $messages[0]->reply());
        $this->assertFalse($messages[0]->reply()?->isSuccess());
    }

    // ---------------------------------------------------------------------------
    // End-to-end: auth handshake through transport + public pipeline
    // ---------------------------------------------------------------------------

    public function test_auth_handshake_sequence_through_transport_and_pipeline(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = InboundPipeline::withDefaults();

        // Step 1: Server sends auth/request
        $transport->enqueueInbound(EslFixtureBuilder::authRequest());
        $pipeline->push($transport->read(4096));
        $messages = $pipeline->drain();

        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->isServerAuthRequest());

        // Step 2: Client writes auth command
        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $this->assertSame("auth ClueCon\n\n", $transport->drainOutbound());

        // Step 3: Server sends auth accepted
        $transport->enqueueInbound(EslFixtureBuilder::authAccepted());
        $pipeline->push($transport->read(4096));
        $messages = $pipeline->drain();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(AuthAcceptedReply::class, $messages[0]->reply());
    }

    public function test_auth_rejection_is_observable_through_transport_and_pipeline(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = InboundPipeline::withDefaults();

        // Server sends auth/request, client sends auth
        $transport->enqueueInbound(EslFixtureBuilder::authRequest());
        $pipeline->push($transport->read(4096));
        $pipeline->drain(); // discard the auth/request notice

        $transport->write((new AuthCommand('wrong-password'))->serialize());
        $this->assertSame("auth wrong-password\n\n", $transport->drainOutbound());

        // Server rejects auth
        $transport->enqueueInbound(EslFixtureBuilder::authRejected());
        $pipeline->push($transport->read(4096));
        $messages = $pipeline->drain();

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(ErrorReply::class, $messages[0]->reply());
        $this->assertFalse($messages[0]->reply()?->isSuccess());
    }

    // ---------------------------------------------------------------------------
    // End-to-end: full connect-and-subscribe sequence
    // ---------------------------------------------------------------------------

    public function test_full_connect_and_subscribe_sequence_through_transport_and_pipeline(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = InboundPipeline::withDefaults();

        // ── auth phase ────────────────────────────────────────────────────────
        $transport->enqueueInbound(EslFixtureBuilder::authRequest());
        $pipeline->push($transport->read(4096));
        $authRequest = $pipeline->drain();

        $this->assertCount(1, $authRequest);
        $this->assertTrue($authRequest[0]->isServerAuthRequest());

        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $this->assertSame("auth ClueCon\n\n", $transport->drainOutbound());

        $transport->enqueueInbound(EslFixtureBuilder::authAccepted());
        $pipeline->push($transport->read(4096));
        $authResult = $pipeline->drain();

        $this->assertCount(1, $authResult);
        $this->assertInstanceOf(AuthAcceptedReply::class, $authResult[0]->reply());

        // ── subscription phase ────────────────────────────────────────────────
        $transport->write(EventSubscriptionCommand::all()->serialize());
        $this->assertSame("event plain all\n\n", $transport->drainOutbound());

        $transport->enqueueInbound(EslFixtureBuilder::commandReplyOk('+OK Events Enabled'));
        $pipeline->push($transport->read(4096));
        $subResult = $pipeline->drain();

        $this->assertCount(1, $subResult);
        $this->assertInstanceOf(CommandReply::class, $subResult[0]->reply());
        $this->assertTrue($subResult[0]->reply()?->isSuccess());
    }

    public function test_json_subscribe_sequence_is_parallel_to_plain(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = InboundPipeline::withDefaults();

        // Auth phase (abbreviated — pin the different subscription wire bytes)
        $transport->enqueueInbound(EslFixtureBuilder::authRequest());
        $pipeline->push($transport->read(4096));
        $pipeline->drain();

        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $transport->drainOutbound();

        $transport->enqueueInbound(EslFixtureBuilder::authAccepted());
        $pipeline->push($transport->read(4096));
        $pipeline->drain();

        // Subscribe with JSON format
        $transport->write(EventSubscriptionCommand::all(EventFormat::Json)->serialize());
        $this->assertSame("event json all\n\n", $transport->drainOutbound());

        $transport->enqueueInbound(EslFixtureBuilder::commandReplyOk('+OK Events Enabled'));
        $pipeline->push($transport->read(4096));
        $subResult = $pipeline->drain();

        $this->assertCount(1, $subResult);
        $this->assertInstanceOf(CommandReply::class, $subResult[0]->reply());
    }

    public function test_noevents_and_resubscribe_sequence_through_transport_and_pipeline(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = InboundPipeline::withDefaults();

        // Abbreviated auth phase
        $transport->enqueueInbound(EslFixtureBuilder::authRequest());
        $pipeline->push($transport->read(4096));
        $pipeline->drain();
        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $transport->drainOutbound();
        $transport->enqueueInbound(EslFixtureBuilder::authAccepted());
        $pipeline->push($transport->read(4096));
        $pipeline->drain();

        // Send noevents
        $transport->write((new NoEventsCommand())->serialize());
        $this->assertSame("noevents\n\n", $transport->drainOutbound());

        $transport->enqueueInbound(EslFixtureBuilder::commandReplyOk());
        $pipeline->push($transport->read(4096));
        $noEventsResult = $pipeline->drain();

        $this->assertCount(1, $noEventsResult);
        $this->assertInstanceOf(CommandReply::class, $noEventsResult[0]->reply());

        // Resubscribe with specific names
        $transport->write(
            EventSubscriptionCommand::forNames(['BACKGROUND_JOB', 'CHANNEL_HANGUP'])->serialize()
        );
        $this->assertSame(
            "event plain BACKGROUND_JOB CHANNEL_HANGUP\n\n",
            $transport->drainOutbound()
        );

        $transport->enqueueInbound(EslFixtureBuilder::commandReplyOk('+OK Events Enabled'));
        $pipeline->push($transport->read(4096));
        $resubResult = $pipeline->drain();

        $this->assertCount(1, $resubResult);
        $this->assertInstanceOf(CommandReply::class, $resubResult[0]->reply());
        $this->assertTrue($resubResult[0]->reply()?->isSuccess());
    }
}
