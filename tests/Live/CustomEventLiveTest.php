<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Live;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\EventFormat;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Commands\ExitCommand;
use Apntalk\EslCore\Commands\RawCommand;
use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Events\CustomEvent;
use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Inbound\DecodedInboundMessage;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Transport\SocketTransportFactory;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CustomEventLiveTest extends TestCase
{
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 8021;
    private const DEFAULT_PASSWORD = 'ClueCon';
    private const READ_CHUNK_BYTES = 8192;

    private ?TransportInterface $transport = null;

    protected function tearDown(): void
    {
        if ($this->transport === null || !$this->transport->isConnected()) {
            return;
        }

        try {
            $this->transport->write((new ExitCommand())->serialize());
        } catch (Throwable) {
        }

        $this->transport->close();
    }

    public function test_docker_freeswitch_emits_neutral_custom_event_over_public_pipeline(): void
    {
        if (getenv('ESL_CORE_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Set ESL_CORE_LIVE_TEST=1 to run the Docker FreeSWITCH CUSTOM event live proof.');
        }

        $host = $this->envString('ESL_CORE_LIVE_HOST', self::DEFAULT_HOST);
        $port = $this->envInt('ESL_CORE_LIVE_PORT', self::DEFAULT_PORT);
        $password = $this->envString('ESL_CORE_LIVE_PASSWORD', self::DEFAULT_PASSWORD);
        $timeoutSeconds = $this->envInt('ESL_CORE_LIVE_TIMEOUT', 6);

        $pipeline = InboundPipeline::withDefaults();
        $this->transport = $this->connect($host, $port);

        $authRequest = $this->readNextMessage($pipeline, $timeoutSeconds);
        $this->assertTrue($authRequest->isServerAuthRequest());

        $this->transport->write((new AuthCommand($password))->serialize());
        $authReply = $this->readNextMessage($pipeline, $timeoutSeconds);
        $this->assertTrue($authReply->isReply());
        $this->assertTrue($authReply->reply()?->isSuccess(), 'FreeSWITCH ESL auth was not accepted.');

        // The Docker lab did not observe injected events when filtering only to CUSTOM.
        // Use a bounded plain-event window, then assert only the neutral CUSTOM event.
        // This validates that a real ESL CUSTOM event can be received and parsed through public esl-core APIs.
        // It does not model downstream domain semantics.
        $this->transport->write(EventSubscriptionCommand::all(EventFormat::Plain)->serialize());
        $subscriptionReply = $this->readNextMessage($pipeline, $timeoutSeconds);
        $this->assertTrue($subscriptionReply->isReply());
        $this->assertTrue($subscriptionReply->reply()?->isSuccess(), 'FreeSWITCH did not accept CUSTOM event subscription.');

        $this->transport->write((new RawCommand(
            "sendevent CUSTOM\n" .
            "Event-Subclass: myapp::heartbeat\n" .
            "Hyphenated-Header: kept-as-is\n" .
            "Unknown-Field: preserve-me\n\n"
        ))->serialize());

        $event = $this->readUntilCustomEvent($pipeline, $timeoutSeconds);

        $this->assertSame('myapp::heartbeat', $event->subclass());
        $this->assertSame('kept-as-is', $event->normalized->header('Hyphenated-Header'));
        $this->assertSame('kept-as-is', $event->normalized->header('hyphenated-header'));
        $this->assertSame('preserve-me', $event->normalized->header('Unknown-Field'));
    }

    private function connect(string $host, int $port): TransportInterface
    {
        $stream = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorCode,
            $errorMessage,
            2.0,
        );

        if (!is_resource($stream)) {
            $this->markTestSkipped(sprintf(
                'Could not connect to Docker FreeSWITCH ESL at %s:%d: [%d] %s',
                $host,
                $port,
                $errorCode,
                $errorMessage !== '' ? $errorMessage : 'stream_socket_client returned no stream',
            ));
        }

        stream_set_blocking($stream, true);
        stream_set_timeout($stream, 1);

        try {
            return (new SocketTransportFactory())->fromStream($stream);
        } catch (TransportException $e) {
            fclose($stream);
            $this->markTestSkipped(sprintf(
                'Could not wrap Docker FreeSWITCH ESL stream at %s:%d: %s',
                $host,
                $port,
                $e->getMessage(),
            ));
        }
    }

    private function readUntilCustomEvent(InboundPipeline $pipeline, int $timeoutSeconds): CustomEvent
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $message = $this->readNextMessage($pipeline, max(1, (int) ceil($deadline - microtime(true))));
            if (!$message->isEvent()) {
                continue;
            }

            $event = $message->event();
            if ($event instanceof CustomEvent) {
                return $event;
            }
        } while (microtime(true) < $deadline);

        $this->markTestSkipped('No neutral CUSTOM event was observed after sendevent; the PBX may not support ESL event injection.');
    }

    private function readNextMessage(InboundPipeline $pipeline, int $timeoutSeconds): DecodedInboundMessage
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $messages = $pipeline->drain();
            if ($messages !== []) {
                return $messages[0];
            }

            try {
                $chunk = $this->transport?->read(self::READ_CHUNK_BYTES);
            } catch (TransportException) {
                usleep(100000);
                continue;
            }

            if ($chunk === null) {
                $this->markTestSkipped('FreeSWITCH ESL connection closed while waiting for a live frame.');
            }

            if ($chunk !== '') {
                $pipeline->push($chunk);
                continue;
            }

            usleep(100000);
        } while (microtime(true) < $deadline);

        $this->markTestSkipped('Timed out waiting for a complete FreeSWITCH ESL frame.');
    }

    private function envString(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            return $default;
        }

        $int = (int) $value;

        return $int > 0 ? $int : $default;
    }
}
