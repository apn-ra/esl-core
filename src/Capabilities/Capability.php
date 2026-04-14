<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Capabilities;

/**
 * Named capabilities that can be declared in a CapabilityMap.
 *
 * Capabilities reflect actual tested behavior. Do not declare a capability
 * unless it is implemented and covered by tests.
 *
 * @api
 */
enum Capability: string
{
    case Auth              = 'auth';
    case ApiCommand        = 'api-command';
    case BgapiCommand      = 'bgapi-command';
    case InboundDecodingFacade = 'inbound-decoding-facade';
    case ReplyParsing      = 'reply-parsing';
    case EventSubscription = 'event-subscription';
    case EventPlainDecoding = 'event-plain-decoding';
    case EventJsonDecoding = 'event-json-decoding';
    case NormalizedEvents  = 'normalized-events';
    case CorrelationMetadata = 'correlation-metadata';
    case ReplayEnvelopeExport = 'replay-envelope-export';
    case ReconstructionHookSupport = 'reconstruction-hook-support';
    case InMemoryTransport = 'in-memory-transport';
    case FixtureReplayCompatibility = 'fixture-replay-compatibility';
}
