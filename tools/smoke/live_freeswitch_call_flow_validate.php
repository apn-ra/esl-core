<?php

declare(strict_types=1);

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\ApiCommand;
use Apntalk\EslCore\Commands\BgapiCommand;
use Apntalk\EslCore\Commands\EventFormat;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Commands\ExitCommand;
use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\PlaybackEvent;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    $options = CallFlowOptions::fromArgv($argv);
    $session = new ControlledCallFlowSession(
        $options->host,
        $options->port,
        $options->resolvePassword(),
        $options->captureWriter(),
    );

    $result = $session->run($options);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['ok'] ?? false) === true ? 0 : 1);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => [
            'class' => $e::class,
            'message' => $e->getMessage(),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

final readonly class CallFlowOptions
{
    private const TARGET_EVENTS = [
        'CHANNEL_BRIDGE',
        'CHANNEL_UNBRIDGE',
        'PLAYBACK_START',
        'PLAYBACK_STOP',
    ];

    private const DIAGNOSTIC_EVENTS = [
        'BACKGROUND_JOB',
        'CHANNEL_CREATE',
        'CHANNEL_ANSWER',
        'CHANNEL_HANGUP',
        'CHANNEL_HANGUP_COMPLETE',
        'CHANNEL_DESTROY',
    ];

    private function __construct(
        public string $host,
        public int $port,
        public EventFormat $format,
        public int $timeoutSeconds,
        public bool $trigger,
        public string $context,
        public string $extension,
        public int $originateTimeoutSeconds,
        public bool $controlledBridgeTeardown,
        public int $bridgeTeardownDelayMilliseconds,
        private ?string $password,
        private ?string $passwordEnvName,
        private ?string $captureDir,
    ) {}

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        if (count($argv) < 3) {
            throw new InvalidArgumentException(self::usage());
        }

        $host = $argv[1];
        $port = (int) $argv[2];
        $format = EventFormat::Plain;
        $timeoutSeconds = 15;
        $trigger = true;
        $context = 'apn-esl-core-smoke';
        $extension = 'apn-esl-core-events';
        $originateTimeoutSeconds = 8;
        $controlledBridgeTeardown = true;
        $bridgeTeardownDelayMilliseconds = 500;
        $password = null;
        $passwordEnvName = null;
        $captureDir = null;

        foreach (array_slice($argv, 3) as $argument) {
            if (str_starts_with($argument, '--password=')) {
                $password = substr($argument, strlen('--password='));
                continue;
            }

            if (str_starts_with($argument, '--password-env=')) {
                $passwordEnvName = substr($argument, strlen('--password-env='));
                continue;
            }

            if (str_starts_with($argument, '--format=')) {
                $formatValue = strtolower(substr($argument, strlen('--format=')));
                $format = match ($formatValue) {
                    'plain' => EventFormat::Plain,
                    'json' => EventFormat::Json,
                    default => throw new InvalidArgumentException('Format must be plain or json.'),
                };
                continue;
            }

            if (str_starts_with($argument, '--timeout=')) {
                $timeoutSeconds = max(1, (int) substr($argument, strlen('--timeout=')));
                continue;
            }

            if ($argument === '--no-trigger') {
                $trigger = false;
                continue;
            }

            if (str_starts_with($argument, '--context=')) {
                $context = self::safeDialplanToken(substr($argument, strlen('--context=')), 'context');
                continue;
            }

            if (str_starts_with($argument, '--extension=')) {
                $extension = self::safeDialplanToken(substr($argument, strlen('--extension=')), 'extension');
                continue;
            }

            if (str_starts_with($argument, '--originate-timeout=')) {
                $originateTimeoutSeconds = max(1, (int) substr($argument, strlen('--originate-timeout=')));
                continue;
            }

            if ($argument === '--no-controlled-bridge-teardown') {
                $controlledBridgeTeardown = false;
                continue;
            }

            if (str_starts_with($argument, '--bridge-teardown-delay-ms=')) {
                $bridgeTeardownDelayMilliseconds = max(0, (int) substr($argument, strlen('--bridge-teardown-delay-ms=')));
                continue;
            }

            if (str_starts_with($argument, '--capture-dir=')) {
                $captureDir = substr($argument, strlen('--capture-dir='));
                continue;
            }

            throw new InvalidArgumentException(sprintf('Unknown option "%s".%s', $argument, PHP_EOL . self::usage()));
        }

        if ($port < 1) {
            throw new InvalidArgumentException('Port must be a positive integer.');
        }

        return new self(
            $host,
            $port,
            $format,
            $timeoutSeconds,
            $trigger,
            $context,
            $extension,
            $originateTimeoutSeconds,
            $controlledBridgeTeardown,
            $bridgeTeardownDelayMilliseconds,
            $password,
            $passwordEnvName,
            $captureDir,
        );
    }

    /**
     * @return list<string>
     */
    public function targetEvents(): array
    {
        return self::TARGET_EVENTS;
    }

    /**
     * @return list<string>
     */
    public function subscriptionEvents(): array
    {
        return array_values(array_unique([...self::TARGET_EVENTS, ...self::DIAGNOSTIC_EVENTS]));
    }

    public function resolvePassword(): string
    {
        if ($this->password !== null && $this->password !== '') {
            return $this->password;
        }

        if ($this->passwordEnvName !== null && $this->passwordEnvName !== '') {
            $password = getenv($this->passwordEnvName);
            if (is_string($password) && $password !== '') {
                return $password;
            }

            throw new RuntimeException(sprintf(
                'ESL password was not found in environment variable "%s".',
                $this->passwordEnvName,
            ));
        }

        throw new RuntimeException('ESL password is required. Supply --password=... or --password-env=ENV_VAR.');
    }

    public function captureWriter(): ?CallFlowCaptureWriter
    {
        if ($this->captureDir === null || $this->captureDir === '') {
            return null;
        }

        return CallFlowCaptureWriter::forDirectory($this->captureDir, sprintf('call-flow-%s', strtolower($this->format->value)));
    }

    private static function safeDialplanToken(string $value, string $name): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s may only contain letters, numbers, dot, underscore, and dash.', $name));
        }

        return $value;
    }

    private static function usage(): string
    {
        return 'Usage: php tools/smoke/live_freeswitch_call_flow_validate.php <host> <port> '
            . '[--password=SECRET | --password-env=ENV_VAR] [--format=plain|json] [--timeout=15] '
            . '[--context=apn-esl-core-smoke] [--extension=apn-esl-core-events] '
            . '[--originate-timeout=8] [--no-trigger] [--no-controlled-bridge-teardown] '
            . '[--bridge-teardown-delay-ms=500] [--capture-dir=tools/smoke/captures]';
    }
}

final class ControlledCallFlowSession
{
    private const READ_CHUNK_BYTES = 8192;
    private const SOCKET_READ_TIMEOUT_SECONDS = 1;

    /** @var resource */
    private $stream;
    private string $rawBuffer = '';
    private InboundMessageClassifier $classifier;
    private ReplyFactory $replyFactory;
    private EventFactory $eventFactory;
    private CorrelationContext $correlation;
    private ReplayEnvelopeFactory $replay;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $password,
        private readonly ?CallFlowCaptureWriter $captureWriter,
    ) {
        $stream = stream_socket_client(sprintf('tcp://%s:%d', $this->host, $this->port), $errno, $errstr, 5);

        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('Failed to connect: [%d] %s', $errno, $errstr));
        }

        stream_set_timeout($stream, self::SOCKET_READ_TIMEOUT_SECONDS);
        stream_set_blocking($stream, true);

        $this->stream = $stream;
        $this->classifier = new InboundMessageClassifier();
        $this->replyFactory = new ReplyFactory();
        $this->eventFactory = new EventFactory();
        $sessionId = ConnectionSessionId::generate();
        $this->correlation = new CorrelationContext($sessionId);
        $this->replay = ReplayEnvelopeFactory::withSession($sessionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(CallFlowOptions $options): array
    {
        $observedEvents = [];
        $nonEventFrames = [];
        $bgapiAccepted = null;
        $originatingUuid = self::uuidV4();
        $bridgeObservedAt = null;
        $bridgeTeardownUuid = null;
        $bridgeTeardownSent = false;
        $controlledBridgeTeardown = null;

        $authRequest = $this->readAndClassifyFrame(5);
        $this->write((new AuthCommand($this->password))->serialize());
        $authReply = $this->readAndClassifyFrame(5);

        $this->write(EventSubscriptionCommand::forNames($options->subscriptionEvents(), $options->format)->serialize());
        $subscriptionReply = $this->readAndClassifyFrame(5);

        $triggerCommand = null;
        if ($options->trigger) {
            $triggerCommand = new BgapiCommand('originate', sprintf(
                '{origination_uuid=%s,originate_timeout=%d,ignore_early_media=true,apn_esl_core_smoke=true}'
                . 'loopback/%s/%s &park()',
                $originatingUuid,
                $options->originateTimeoutSeconds,
                $options->extension,
                $options->context,
            ));
            $this->write($triggerCommand->serialize());
        }

        $deadline = microtime(true) + $options->timeoutSeconds;
        while (microtime(true) < $deadline && !$this->hasObservedAllTargets($observedEvents, $options->targetEvents())) {
            if (
                $options->controlledBridgeTeardown
                && !$bridgeTeardownSent
                && $bridgeObservedAt !== null
                && $bridgeTeardownUuid !== null
            ) {
                $teardownAt = $bridgeObservedAt + ($options->bridgeTeardownDelayMilliseconds / 1000);
                $remainingDelayMicros = (int) max(0, ($teardownAt - microtime(true)) * 1_000_000);
                if ($remainingDelayMicros > 0) {
                    usleep($remainingDelayMicros);
                }

                $teardownCommand = new ApiCommand('uuid_kill', sprintf('%s NORMAL_CLEARING', $bridgeTeardownUuid));
                $this->write($teardownCommand->serialize());
                $bridgeTeardownSent = true;
                $controlledBridgeTeardown = [
                    'peer_uuid' => $bridgeTeardownUuid,
                    'delay_ms' => $options->bridgeTeardownDelayMilliseconds,
                    'command' => $teardownCommand->serialize(),
                ];
            }

            try {
                $frame = $this->readFrame((int) max(1, ceil($deadline - microtime(true))));
            } catch (RuntimeException) {
                break;
            }

            $classified = $this->classifier->classify($frame);

            if ($classified->category === InboundMessageCategory::EventMessage) {
                $event = $this->eventFactory->fromFrame($frame);
                $summary = $this->eventSummary($event);
                $observedEvents[] = $summary;

                if ($event instanceof BridgeEvent && $event->eventName() === 'CHANNEL_BRIDGE' && $bridgeObservedAt === null) {
                    $bridgeObservedAt = microtime(true);
                    $bridgeTeardownUuid = $event->otherLegUniqueId() ?? $event->uniqueId();
                }

                continue;
            }

            $reply = $this->replyFactory->fromClassified($classified);
            $summary = [
                'category' => $classified->category->name,
                'message_type' => $classified->messageType->value,
                'typed_reply_class' => $reply::class,
                'is_success' => $reply->isSuccess(),
            ];
            $nonEventFrames[] = $summary;

            if ($reply instanceof BgapiAcceptedReply) {
                $bgapiAccepted = $summary + ['job_uuid' => $reply->jobUuid()];
            }
        }

        $disconnect = $this->gracefulExit();
        $observedTargetNames = array_values(array_unique(array_map(
            static fn(array $event): string => (string) $event['event_name'],
            array_filter(
                $observedEvents,
                static fn(array $event): bool => in_array($event['event_name'], $options->targetEvents(), true),
            ),
        )));
        sort($observedTargetNames);

        $missing = array_values(array_diff($options->targetEvents(), $observedTargetNames));

        return [
            'ok' => $missing === [],
            'target' => [
                'host' => $this->host,
                'port' => $this->port,
            ],
            'mode' => sprintf('call-flow-%s', strtolower($options->format->value)),
            'flow' => [
                'triggered' => $options->trigger,
                'context' => $options->context,
                'extension' => $options->extension,
                'originating_uuid' => $originatingUuid,
                'subscribed_events' => $options->subscriptionEvents(),
                'target_events' => $options->targetEvents(),
                'trigger_command' => $triggerCommand?->serialize(),
                'bgapi_accepted' => $bgapiAccepted,
                'controlled_bridge_teardown' => $controlledBridgeTeardown,
            ],
            'auth_request' => $this->classifiedSummary($authRequest),
            'auth_reply' => $this->classifiedSummary($authReply),
            'subscription_reply' => $this->classifiedSummary($subscriptionReply),
            'observed_target_events' => $observedTargetNames,
            'missing_target_events' => $missing,
            'events' => $observedEvents,
            'non_event_frames' => $nonEventFrames,
            'disconnect' => $disconnect,
            'captures' => $this->captureWriter?->capturedArtifacts() ?? [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $observedEvents
     * @param list<string> $targetEvents
     */
    private function hasObservedAllTargets(array $observedEvents, array $targetEvents): bool
    {
        $observed = array_map(
            static fn(array $event): string => (string) $event['event_name'],
            $observedEvents,
        );

        return array_diff($targetEvents, $observed) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(EventInterface $event): array
    {
        $metadata = $this->correlation->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replayEnvelope = $this->replay->fromEventEnvelope($envelope);

        return [
            'event_name' => $event->eventName(),
            'typed_event_class' => $event::class,
            'is_bridge_event' => $event instanceof BridgeEvent,
            'is_playback_event' => $event instanceof PlaybackEvent,
            'unique_id' => $event->uniqueId(),
            'job_uuid' => $event->jobUuid(),
            'core_uuid' => $event->coreUuid(),
            'event_sequence' => $event->eventSequence(),
            'other_leg_unique_id' => $event instanceof BridgeEvent ? $event->otherLegUniqueId() : null,
            'other_leg_channel_name' => $event instanceof BridgeEvent ? $event->otherLegChannelName() : null,
            'body' => $event instanceof BackgroundJobEvent ? $event->result() : null,
            'metadata' => [
                'session_id' => $envelope->sessionId()?->toString(),
                'observation_sequence' => $envelope->observationSequence()->position(),
                'channel_correlation' => $envelope->channelCorrelation()?->uniqueId(),
                'protocol_sequence' => $envelope->metadata()->protocolSequence(),
            ],
            'replay' => [
                'captured_type' => $replayEnvelope->capturedType(),
                'captured_name' => $replayEnvelope->capturedName(),
                'capture_sequence' => $replayEnvelope->captureSequence(),
                'protocol_sequence' => $replayEnvelope->protocolSequence(),
                'protocol_facts' => $replayEnvelope->protocolFacts(),
                'derived_metadata' => $replayEnvelope->derivedMetadata(),
            ],
        ];
    }

    private function readAndClassifyFrame(int $timeoutSeconds): ClassifiedInboundMessage
    {
        return $this->classifier->classify($this->readFrame($timeoutSeconds));
    }

    private function readFrame(int $timeoutSeconds): Frame
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $rawFrame = $this->tryExtractRawFrame();
            if ($rawFrame !== null) {
                $this->captureWriter?->store($rawFrame, 'full-frame');
                return $this->parseSingleFrame($rawFrame);
            }

            $chunk = fread($this->stream, self::READ_CHUNK_BYTES);
            $meta = stream_get_meta_data($this->stream);

            if ($chunk === false) {
                $this->captureResidualBuffer('read-false');
                throw new RuntimeException('Failed to read from ESL socket.');
            }

            if ($chunk !== '') {
                $this->rawBuffer .= $chunk;
                continue;
            }

            if ($meta['timed_out'] === true) {
                usleep(100000);
                continue;
            }

            if ($meta['eof'] === true) {
                $this->captureResidualBuffer('remote-eof');
                throw new RuntimeException('ESL socket reached EOF.');
            }
        }

        $this->captureResidualBuffer('overall-timeout');
        throw new RuntimeException('Timed out waiting for a complete ESL frame.');
    }

    private function parseSingleFrame(string $rawFrame): Frame
    {
        $parser = new FrameParser();
        $parser->feed($rawFrame);
        $frames = $parser->drain();
        $parser->finish();

        if (count($frames) !== 1) {
            throw new RuntimeException(sprintf('Expected exactly one parsed frame, got %d.', count($frames)));
        }

        return $frames[0];
    }

    private function tryExtractRawFrame(): ?string
    {
        $delimiterPosition = strpos($this->rawBuffer, "\n\n");
        if ($delimiterPosition === false) {
            return null;
        }

        $headerBlock = substr($this->rawBuffer, 0, $delimiterPosition);
        $contentLength = 0;
        foreach (explode("\n", $headerBlock) as $line) {
            $line = rtrim($line, "\r");
            if (str_starts_with(strtolower($line), 'content-length:')) {
                $contentLength = (int) trim(substr($line, strlen('content-length:')));
                break;
            }
        }

        $frameLength = $delimiterPosition + 2 + $contentLength;
        if (strlen($this->rawBuffer) < $frameLength) {
            return null;
        }

        $rawFrame = substr($this->rawBuffer, 0, $frameLength);
        $this->rawBuffer = substr($this->rawBuffer, $frameLength);

        return $rawFrame;
    }

    private function write(string $wire): void
    {
        $written = fwrite($this->stream, $wire);
        if ($written === false || $written !== strlen($wire)) {
            throw new RuntimeException('Failed to write full command to ESL socket.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function classifiedSummary(ClassifiedInboundMessage $classified): array
    {
        return [
            'category' => $classified->category->name,
            'message_type' => $classified->messageType->value,
            'headers' => $classified->frame->headers->toArray(),
            'body' => $classified->frame->body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gracefulExit(): array
    {
        $this->write((new ExitCommand())->serialize());

        try {
            return $this->classifiedSummary($this->readAndClassifyFrame(3));
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function captureResidualBuffer(string $reason): void
    {
        if ($this->rawBuffer === '') {
            return;
        }

        $kind = str_contains($this->rawBuffer, "\n\n") ? 'partial-frame' : 'trailing-fragment';
        $this->captureWriter?->store($this->rawBuffer, sprintf('%s-%s', $reason, $kind), 'raw');
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}

final class CallFlowCaptureWriter
{
    private int $sequence = 0;

    /** @var list<array{path: string, kind: string, bytes: int}> */
    private array $capturedArtifacts = [];

    private function __construct(
        private readonly string $directory,
        private readonly string $mode,
    ) {}

    public static function forDirectory(string $directory, string $mode): self
    {
        $absolute = str_starts_with($directory, DIRECTORY_SEPARATOR)
            ? $directory
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($absolute) && !mkdir($absolute, 0o777, true) && !is_dir($absolute)) {
            throw new RuntimeException(sprintf('Failed to create capture directory: %s', $absolute));
        }

        return new self($absolute, $mode);
    }

    public function store(string $payload, string $kind, string $extension = 'esl'): void
    {
        $this->sequence++;
        $filename = sprintf(
            '%s-%s-%03d-%s-%s.%s',
            gmdate('Ymd\THis\Z'),
            preg_replace('/[^a-z0-9-]+/i', '-', strtolower($this->mode)) ?? 'call-flow',
            $this->sequence,
            preg_replace('/[^a-z0-9-]+/i', '-', strtolower($kind)) ?? 'artifact',
            bin2hex(random_bytes(4)),
            preg_replace('/[^a-z0-9]+/i', '', strtolower($extension)) ?: 'bin',
        );

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        $written = file_put_contents($path, $payload);

        if ($written === false || $written !== strlen($payload)) {
            throw new RuntimeException(sprintf('Failed to write call-flow capture: %s', $path));
        }

        $this->capturedArtifacts[] = [
            'path' => $path,
            'kind' => $kind,
            'bytes' => $written,
        ];
    }

    /**
     * @return list<array{path: string, kind: string, bytes: int}>
     */
    public function capturedArtifacts(): array
    {
        return $this->capturedArtifacts;
    }
}
