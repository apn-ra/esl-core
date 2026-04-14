<?php

declare(strict_types=1);

use Apntalk\EslCore\Commands\ApiCommand;
use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\EventFormat;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Commands\ExitCommand;
use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    $options = LiveValidationOptions::fromArgv($argv);
    $session = new LiveEslReadonlySession(
        $options->host,
        $options->port,
        $options->resolvePassword(),
        $options->captureWriter(),
    );

    $result = match ($options->mode) {
        'auth' => $session->validateAuthOnly(),
        'api' => $session->validateApiStatus(),
        'event-plain' => $session->validateEvents(EventFormat::Plain, $options->timeoutSeconds),
        'event-json' => $session->validateEvents(EventFormat::Json, $options->timeoutSeconds),
        default => throw new InvalidArgumentException("Unknown mode: {$options->mode}"),
    };

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['ok'] ?? false) === true ? 0 : 1);
} catch (Throwable $e) {
    $payload = [
        'ok' => false,
        'error' => [
            'class' => $e::class,
            'message' => $e->getMessage(),
        ],
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

final readonly class LiveValidationOptions
{
    private function __construct(
        public string $mode,
        public string $host,
        public int $port,
        public int $timeoutSeconds,
        private ?string $password,
        private ?string $passwordEnvName,
        private ?string $captureDir,
    ) {}

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        if (count($argv) < 4) {
            throw new InvalidArgumentException(self::usage());
        }

        $mode = $argv[1];
        $host = $argv[2];
        $port = (int) $argv[3];
        $timeoutSeconds = 8;
        $password = null;
        $passwordEnvName = null;
        $captureDir = null;

        foreach (array_slice($argv, 4) as $argument) {
            if (str_starts_with($argument, '--password=')) {
                $password = substr($argument, strlen('--password='));
                continue;
            }

            if (str_starts_with($argument, '--password-env=')) {
                $passwordEnvName = substr($argument, strlen('--password-env='));
                continue;
            }

            if (str_starts_with($argument, '--timeout=')) {
                $timeoutSeconds = max(1, (int) substr($argument, strlen('--timeout=')));
                continue;
            }

            if (str_starts_with($argument, '--capture-dir=')) {
                $captureDir = substr($argument, strlen('--capture-dir='));
                continue;
            }

            throw new InvalidArgumentException(
                sprintf('Unknown option "%s".%s', $argument, PHP_EOL . self::usage())
            );
        }

        if ($port < 1) {
            throw new InvalidArgumentException('Port must be a positive integer.');
        }

        return new self(
            $mode,
            $host,
            $port,
            $timeoutSeconds,
            $password,
            $passwordEnvName,
            $captureDir,
        );
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
                $this->passwordEnvName
            ));
        }

        throw new RuntimeException(
            'ESL password is required. Supply --password=... or --password-env=ENV_VAR.'
        );
    }

    public function captureWriter(): ?RawFrameCaptureWriter
    {
        if ($this->captureDir === null || $this->captureDir === '') {
            return null;
        }

        return RawFrameCaptureWriter::forDirectory($this->captureDir, $this->mode);
    }

    private static function usage(): string
    {
        return 'Usage: php tools/smoke/live_freeswitch_readonly_validate.php <mode> <host> <port> '
            . '[--password=SECRET | --password-env=ENV_VAR] [--timeout=8] [--capture-dir=tools/smoke/captures]';
    }
}

final class LiveEslReadonlySession
{
    private const READ_CHUNK_BYTES = 8192;

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
        private readonly ?RawFrameCaptureWriter $captureWriter = null,
    ) {
        $stream = stream_socket_client(
            sprintf('tcp://%s:%d', $this->host, $this->port),
            $errno,
            $errstr,
            5
        );

        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('Failed to connect: [%d] %s', $errno, $errstr));
        }

        stream_set_timeout($stream, 1);
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
    public function validateAuthOnly(): array
    {
        $authRequest = $this->readClassifiedFrame(5);
        $this->write((new AuthCommand($this->password))->serialize());
        [$replyClassified, $reply, $replyEnvelope, $replayEnvelope] = $this->readReplyEnvelope(5);
        $disconnect = $this->gracefulExit();

        return [
            'ok' => $reply->isSuccess(),
            'mode' => 'auth',
            'auth_request' => $this->classifiedSummary($authRequest),
            'auth_reply' => $this->replyResultSummary($replyClassified, $reply, $replyEnvelope, $replayEnvelope),
            'disconnect' => $disconnect,
            'captures' => $this->captureManifest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validateApiStatus(): array
    {
        $auth = $this->validateAuthHandshake();
        if (($auth['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'mode' => 'api',
                'auth' => $auth,
                'error' => 'Auth was rejected by FreeSWITCH.',
                'captures' => $this->captureManifest(),
            ];
        }

        $command = new ApiCommand('status');
        $this->write($command->serialize());
        [$classified, $reply, $replyEnvelope, $replayEnvelope] = $this->readReplyEnvelope(5);
        $disconnect = $this->gracefulExit();

        return [
            'ok' => true,
            'mode' => 'api',
            'auth' => $auth,
            'command' => [
                'wire' => $command->serialize(),
            ],
            'reply' => $this->replyResultSummary($classified, $reply, $replyEnvelope, $replayEnvelope),
            'disconnect' => $disconnect,
            'captures' => $this->captureManifest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validateEvents(EventFormat $format, int $timeoutSeconds): array
    {
        $auth = $this->validateAuthHandshake();
        if (($auth['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'mode' => sprintf('event-%s', strtolower($format->value)),
                'auth' => $auth,
                'error' => 'Auth was rejected by FreeSWITCH.',
                'captures' => $this->captureManifest(),
            ];
        }

        $eventNames = $format === EventFormat::Plain
            ? ['HEARTBEAT', 'CHANNEL_BRIDGE', 'CHANNEL_UNBRIDGE', 'PLAYBACK_START', 'PLAYBACK_STOP']
            : ['HEARTBEAT'];

        $command = EventSubscriptionCommand::forNames($eventNames, $format);
        $this->write($command->serialize());
        [$subscriptionClassified, $subscriptionReply, $replyEnvelope, $replyReplay] = $this->readReplyEnvelope(5);
        $events = $this->collectEvents($timeoutSeconds);
        $disconnect = $this->gracefulExit();

        return [
            'ok' => true,
            'mode' => sprintf('event-%s', strtolower($format->value)),
            'auth' => $auth,
            'command' => [
                'wire' => $command->serialize(),
                'event_names' => $eventNames,
            ],
            'subscription_reply' => $this->replyResultSummary(
                $subscriptionClassified,
                $subscriptionReply,
                $replyEnvelope,
                $replyReplay,
            ),
            'events' => $events,
            'disconnect' => $disconnect,
            'captures' => $this->captureManifest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAuthHandshake(): array
    {
        $authRequest = $this->readClassifiedFrame(5);
        $this->write((new AuthCommand($this->password))->serialize());
        [$classified, $reply, $replyEnvelope, $replayEnvelope] = $this->readReplyEnvelope(5);

        return [
            'ok' => $reply->isSuccess(),
            'auth_request' => $this->classifiedSummary($authRequest),
            'auth_reply' => $this->replyResultSummary($classified, $reply, $replyEnvelope, $replayEnvelope),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gracefulExit(): array
    {
        $this->write((new ExitCommand())->serialize());

        try {
            $classified = $this->readClassifiedFrame(3);
            return $this->classifiedSummary($classified);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function write(string $wire): void
    {
        $written = fwrite($this->stream, $wire);
        if ($written === false || $written !== strlen($wire)) {
            throw new RuntimeException('Failed to write full command to ESL socket');
        }
    }

    private function readClassifiedFrame(int $timeoutSeconds): ClassifiedInboundMessage
    {
        $frame = $this->readFrame($timeoutSeconds);
        return $this->classifier->classify($frame);
    }

    /**
     * @return array{ClassifiedInboundMessage, ReplyInterface, ReplyEnvelope, \Apntalk\EslCore\Replay\ReplayEnvelope}
     */
    private function readReplyEnvelope(int $timeoutSeconds): array
    {
        $classified = $this->readClassifiedFrame($timeoutSeconds);
        $reply = $this->replyFactory->fromClassified($classified);
        $metadata = $this->correlation->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);
        $replayEnvelope = $this->replay->fromReplyEnvelope($envelope);

        return [$classified, $reply, $envelope, $replayEnvelope];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectEvents(int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $events = [];

        while (microtime(true) < $deadline) {
            try {
                $frame = $this->readFrame((int) max(1, ceil($deadline - microtime(true))));
            } catch (RuntimeException $e) {
                break;
            }

            $classified = $this->classifier->classify($frame);
            if ($classified->category !== InboundMessageCategory::EventMessage) {
                $events[] = [
                    'kind' => 'non-event-frame',
                    'classified' => $this->classifiedSummary($classified),
                ];
                continue;
            }

            $event = $this->eventFactory->fromFrame($frame);
            $metadata = $this->correlation->nextMetadataForEvent($event);
            $envelope = new EventEnvelope($event, $metadata);
            $replayEnvelope = $this->replay->fromEventEnvelope($envelope);

            $events[] = $this->eventResultSummary($classified, $event, $envelope, $replayEnvelope);
        }

        return $events;
    }

    private function readFrame(int $timeoutSeconds): Frame
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $rawFrame = $this->tryExtractRawFrame();
            if ($rawFrame !== null) {
                $this->captureWriter?->store($rawFrame);
                return $this->parseSingleFrame($rawFrame);
            }

            $chunk = fread($this->stream, self::READ_CHUNK_BYTES);
            if ($chunk === false) {
                throw new RuntimeException('Failed to read from ESL socket');
            }

            if ($chunk !== '') {
                $this->rawBuffer .= $chunk;
                continue;
            }

            $meta = stream_get_meta_data($this->stream);
            if (($meta['timed_out'] ?? false) === true) {
                usleep(100000);
                continue;
            }

            if (($meta['eof'] ?? false) === true) {
                throw new RuntimeException('ESL socket reached EOF');
            }
        }

        throw new RuntimeException('Timed out waiting for a complete ESL frame');
    }

    private function parseSingleFrame(string $rawFrame): Frame
    {
        $parser = new FrameParser();
        $parser->feed($rawFrame);
        $frames = $parser->drain();
        $parser->finish();

        if (count($frames) !== 1) {
            throw new RuntimeException(sprintf(
                'Expected exactly one parsed frame, got %d.',
                count($frames)
            ));
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
        $contentLength = $this->detectContentLength($headerBlock);
        $frameLength = $delimiterPosition + 2 + $contentLength;

        if (strlen($this->rawBuffer) < $frameLength) {
            return null;
        }

        $rawFrame = substr($this->rawBuffer, 0, $frameLength);
        $this->rawBuffer = substr($this->rawBuffer, $frameLength);

        return $rawFrame;
    }

    private function detectContentLength(string $headerBlock): int
    {
        foreach (explode("\n", $headerBlock) as $line) {
            $line = rtrim($line, "\r");
            if (!str_starts_with(strtolower($line), 'content-length:')) {
                continue;
            }

            $value = trim(substr($line, strlen('content-length:')));
            if ($value === '' || !ctype_digit($value)) {
                throw new RuntimeException(sprintf(
                    'Observed invalid Content-Length while capturing raw frame: %s',
                    $line
                ));
            }

            return (int) $value;
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function classifiedSummary(ClassifiedInboundMessage $classified): array
    {
        return [
            'category' => $classified->category->name,
            'message_type' => $classified->messageType->value,
            'raw_frame' => $this->frameToRawString($classified->frame),
            'headers' => $classified->frame->headers->toArray(),
            'body' => $classified->frame->body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function replyResultSummary(
        ClassifiedInboundMessage $classified,
        ReplyInterface $reply,
        ReplyEnvelope $envelope,
        \Apntalk\EslCore\Replay\ReplayEnvelope $replayEnvelope,
    ): array {
        return [
            'classified' => $this->classifiedSummary($classified),
            'typed_reply_class' => $reply::class,
            'is_success' => $reply->isSuccess(),
            'metadata' => [
                'session_id' => $envelope->sessionId()?->toString(),
                'observation_sequence' => $envelope->observationSequence()->position(),
                'job_correlation' => $envelope->jobCorrelation()?->jobUuid(),
            ],
            'replay' => [
                'captured_type' => $replayEnvelope->capturedType(),
                'captured_name' => $replayEnvelope->capturedName(),
                'capture_sequence' => $replayEnvelope->captureSequence(),
                'protocol_facts' => $replayEnvelope->protocolFacts(),
                'derived_metadata' => $replayEnvelope->derivedMetadata(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventResultSummary(
        ClassifiedInboundMessage $classified,
        EventInterface $event,
        EventEnvelope $envelope,
        \Apntalk\EslCore\Replay\ReplayEnvelope $replayEnvelope,
    ): array {
        return [
            'classified' => $this->classifiedSummary($classified),
            'typed_event_class' => $event::class,
            'event_name' => $event->eventName(),
            'unique_id' => $event->uniqueId(),
            'job_uuid' => $event->jobUuid(),
            'core_uuid' => $event->coreUuid(),
            'event_sequence' => $event->eventSequence(),
            'metadata' => [
                'session_id' => $envelope->sessionId()?->toString(),
                'observation_sequence' => $envelope->observationSequence()->position(),
                'job_correlation' => $envelope->jobCorrelation()?->jobUuid(),
                'channel_correlation' => $envelope->channelCorrelation()?->uniqueId(),
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

    /**
     * @return list<string>
     */
    private function captureManifest(): array
    {
        return $this->captureWriter?->capturedFiles() ?? [];
    }

    private function frameToRawString(Frame $frame): string
    {
        $raw = '';
        foreach ($frame->headers->toFlatArray() as $header) {
            $raw .= sprintf("%s: %s\n", $header['name'], $header['value']);
        }

        $raw .= "\n";
        $raw .= $frame->body;

        return $raw;
    }
}

final class RawFrameCaptureWriter
{
    private int $sequence = 0;

    /** @var list<string> */
    private array $capturedFiles = [];

    private function __construct(
        private readonly string $directory,
        private readonly string $mode,
    ) {}

    public static function forDirectory(string $directory, string $mode): self
    {
        $absolute = self::normalizeDirectory($directory);

        if (!is_dir($absolute) && !mkdir($absolute, 0777, true) && !is_dir($absolute)) {
            throw new RuntimeException(sprintf('Failed to create capture directory: %s', $absolute));
        }

        return new self($absolute, $mode);
    }

    public function store(string $rawFrame): void
    {
        $this->sequence++;
        $filename = sprintf(
            '%s-%s-%03d-%s.esl',
            gmdate('Ymd\THis\Z'),
            preg_replace('/[^a-z0-9-]+/i', '-', strtolower($this->mode)) ?? 'capture',
            $this->sequence,
            bin2hex(random_bytes(4))
        );
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        $written = file_put_contents($path, $rawFrame);
        if ($written === false || $written !== strlen($rawFrame)) {
            throw new RuntimeException(sprintf('Failed to write raw frame capture: %s', $path));
        }

        $this->capturedFiles[] = $path;
    }

    /**
     * @return list<string>
     */
    public function capturedFiles(): array
    {
        return $this->capturedFiles;
    }

    private static function normalizeDirectory(string $directory): string
    {
        if (str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            return $directory;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($directory, DIRECTORY_SEPARATOR);
    }
}
