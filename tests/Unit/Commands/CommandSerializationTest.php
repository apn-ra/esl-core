<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Commands;

use Apntalk\EslCore\Commands\ApiCommand;
use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\BgapiCommand;
use Apntalk\EslCore\Commands\EventFormat;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Commands\ExitCommand;
use Apntalk\EslCore\Commands\FilterCommand;
use Apntalk\EslCore\Commands\NoEventsCommand;
use Apntalk\EslCore\Commands\RawCommand;
use Apntalk\EslCore\Exceptions\SerializationException;
use PHPUnit\Framework\TestCase;

final class CommandSerializationTest extends TestCase
{
    /**
     * @return iterable<string, array{0: callable(): void, 1: string}>
     */
    public static function invalidTypedCommandProvider(): iterable
    {
        yield 'auth password newline' => [
            static function (): void {
                new AuthCommand("Clue\nCon");
            },
            'must not contain carriage return or newline characters',
        ];

        yield 'api command carriage return' => [
            static function (): void {
                new ApiCommand("status\r");
            },
            'must be a non-empty token without whitespace',
        ];

        yield 'api command empty' => [
            static function (): void {
                new ApiCommand('');
            },
            'Typed command field "command" must be a non-empty token without whitespace.',
        ];

        yield 'api command contains space' => [
            static function (): void {
                new ApiCommand('show channels');
            },
            'Typed command field "command" must be a non-empty token without whitespace.',
        ];

        yield 'api args newline' => [
            static function (): void {
                new ApiCommand('show', "channels\napi hupall");
            },
            'must not contain carriage return or newline characters',
        ];

        yield 'bgapi command empty' => [
            static function (): void {
                new BgapiCommand('');
            },
            'Typed command field "command" must be a non-empty token without whitespace.',
        ];

        yield 'bgapi command contains tab' => [
            static function (): void {
                new BgapiCommand("originate\tbad");
            },
            'Typed command field "command" must be a non-empty token without whitespace.',
        ];

        yield 'bgapi args carriage return' => [
            static function (): void {
                new BgapiCommand('originate', "sofia/internal/1001\r\napi status");
            },
            'must not contain carriage return or newline characters',
        ];

        yield 'filter header name newline' => [
            static function (): void {
                FilterCommand::add("Event-Name\nReply-Text", 'CHANNEL_CREATE');
            },
            'Typed command field "headerName" must be a non-empty token without whitespace.',
        ];

        yield 'filter header name contains space' => [
            static function (): void {
                FilterCommand::add('Event Name', 'CHANNEL_CREATE');
            },
            'Typed command field "headerName" must be a non-empty token without whitespace.',
        ];

        yield 'filter header value carriage return' => [
            static function (): void {
                FilterCommand::delete('Event-Name', "CHANNEL_CREATE\rfilter delete");
            },
            'must not contain carriage return or newline characters',
        ];

        yield 'event subscription name newline' => [
            static function (): void {
                EventSubscriptionCommand::forNames(['CHANNEL_CREATE', "CHANNEL_HANGUP\napi status"]);
            },
            'Typed command field "eventNames[1]" must be a non-empty token without whitespace.',
        ];

        yield 'event subscription name empty' => [
            static function (): void {
                EventSubscriptionCommand::forNames(['CHANNEL_CREATE', '']);
            },
            'Typed command field "eventNames[1]" must be a non-empty token without whitespace.',
        ];

        yield 'event subscription name contains space' => [
            static function (): void {
                EventSubscriptionCommand::forNames(['CHANNEL CREATE']);
            },
            'Typed command field "eventNames[0]" must be a non-empty token without whitespace.',
        ];
    }

    // ---------------------------------------------------------------------------
    // AuthCommand
    // ---------------------------------------------------------------------------

    public function test_auth_command_serializes(): void
    {
        $cmd = new AuthCommand('ClueCon');
        $this->assertSame("auth ClueCon\n\n", $cmd->serialize());
    }

    public function test_auth_command_preserves_password(): void
    {
        $cmd = new AuthCommand('my-secret-password');
        $this->assertSame('my-secret-password', $cmd->password());
    }

    public function test_auth_command_ends_with_double_newline(): void
    {
        $cmd = new AuthCommand('pass');
        $this->assertStringEndsWith("\n\n", $cmd->serialize());
    }

    // ---------------------------------------------------------------------------
    // ApiCommand
    // ---------------------------------------------------------------------------

    public function test_api_command_without_args(): void
    {
        $cmd = new ApiCommand('status');
        $this->assertSame("api status\n\n", $cmd->serialize());
    }

    public function test_api_command_with_args(): void
    {
        $cmd = new ApiCommand('show', 'channels');
        $this->assertSame("api show channels\n\n", $cmd->serialize());
    }

    public function test_api_command_with_empty_args_omits_trailing_space(): void
    {
        $cmd = new ApiCommand('version', '');
        $this->assertSame("api version\n\n", $cmd->serialize());
    }

    // ---------------------------------------------------------------------------
    // BgapiCommand
    // ---------------------------------------------------------------------------

    public function test_bgapi_command_without_args(): void
    {
        $cmd = new BgapiCommand('status');
        $this->assertSame("bgapi status\n\n", $cmd->serialize());
    }

    public function test_bgapi_command_with_args(): void
    {
        $cmd = new BgapiCommand('originate', 'sofia/internal/1001 &echo()');
        $this->assertSame("bgapi originate sofia/internal/1001 &echo()\n\n", $cmd->serialize());
    }

    // ---------------------------------------------------------------------------
    // EventSubscriptionCommand
    // ---------------------------------------------------------------------------

    public function test_event_all_plain(): void
    {
        $cmd = EventSubscriptionCommand::all();
        $this->assertSame("event plain all\n\n", $cmd->serialize());
    }

    public function test_event_all_json(): void
    {
        $cmd = EventSubscriptionCommand::all(EventFormat::Json);
        $this->assertSame("event json all\n\n", $cmd->serialize());
    }

    public function test_event_specific_names(): void
    {
        $cmd = EventSubscriptionCommand::forNames(['CHANNEL_CREATE', 'CHANNEL_HANGUP']);
        $this->assertSame("event plain CHANNEL_CREATE CHANNEL_HANGUP\n\n", $cmd->serialize());
    }

    public function test_event_single_name(): void
    {
        $cmd = EventSubscriptionCommand::forNames(['BACKGROUND_JOB']);
        $this->assertSame("event plain BACKGROUND_JOB\n\n", $cmd->serialize());
    }

    public function test_event_is_all_events_true_for_empty_list(): void
    {
        $cmd = EventSubscriptionCommand::all();
        $this->assertTrue($cmd->isAllEvents());
    }

    public function test_event_is_all_events_false_for_named_list(): void
    {
        $cmd = EventSubscriptionCommand::forNames(['CHANNEL_CREATE']);
        $this->assertFalse($cmd->isAllEvents());
    }

    // ---------------------------------------------------------------------------
    // FilterCommand
    // ---------------------------------------------------------------------------

    public function test_filter_add(): void
    {
        $cmd = FilterCommand::add('Event-Name', 'CHANNEL_CREATE');
        $this->assertSame("filter Event-Name CHANNEL_CREATE\n\n", $cmd->serialize());
    }

    public function test_filter_delete(): void
    {
        $cmd = FilterCommand::delete('Event-Name', 'CHANNEL_CREATE');
        $this->assertSame("filter delete Event-Name CHANNEL_CREATE\n\n", $cmd->serialize());
    }

    public function test_filter_is_delete_flag(): void
    {
        $add    = FilterCommand::add('Event-Name', 'CHANNEL_CREATE');
        $delete = FilterCommand::delete('Event-Name', 'CHANNEL_CREATE');

        $this->assertFalse($add->isDelete());
        $this->assertTrue($delete->isDelete());
    }

    // ---------------------------------------------------------------------------
    // NoEventsCommand
    // ---------------------------------------------------------------------------

    public function test_no_events_command(): void
    {
        $cmd = new NoEventsCommand();
        $this->assertSame("noevents\n\n", $cmd->serialize());
    }

    // ---------------------------------------------------------------------------
    // ExitCommand
    // ---------------------------------------------------------------------------

    public function test_exit_command(): void
    {
        $cmd = new ExitCommand();
        $this->assertSame("exit\n\n", $cmd->serialize());
    }

    // ---------------------------------------------------------------------------
    // RawCommand
    // ---------------------------------------------------------------------------

    public function test_raw_command_returns_exact_string(): void
    {
        $cmd = new RawCommand("linger\n\n");
        $this->assertSame("linger\n\n", $cmd->serialize());
    }

    public function test_raw_command_throws_when_missing_double_newline(): void
    {
        $this->expectException(SerializationException::class);
        new RawCommand("linger\n");
    }

    public function test_raw_command_throws_for_no_newline(): void
    {
        $this->expectException(SerializationException::class);
        new RawCommand("linger");
    }

    public function test_raw_command_allows_complex_multiline(): void
    {
        $raw = "sendevent CUSTOM\nEvent-Subclass: myapp::event\n\n";
        $cmd = new RawCommand($raw);
        $this->assertSame($raw, $cmd->serialize());
    }

    /**
     * @dataProvider invalidTypedCommandProvider
     */
    public function test_typed_commands_reject_invalid_user_provided_fields(callable $factory, string $message): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($message);

        $factory();
    }

    // ---------------------------------------------------------------------------
    // All commands end with \n\n
    // ---------------------------------------------------------------------------

    public function test_all_typed_commands_end_with_double_newline(): void
    {
        $commands = [
            new AuthCommand('pass'),
            new ApiCommand('status'),
            new BgapiCommand('status'),
            EventSubscriptionCommand::all(),
            FilterCommand::add('Event-Name', 'CHANNEL_CREATE'),
            new NoEventsCommand(),
            new ExitCommand(),
        ];

        foreach ($commands as $cmd) {
            $this->assertStringEndsWith(
                "\n\n",
                $cmd->serialize(),
                get_class($cmd) . ' must end with \n\n'
            );
        }
    }
}
