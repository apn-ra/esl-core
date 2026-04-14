<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Fixtures;

/**
 * Builds deterministic ESL protocol byte strings for use in tests.
 *
 * All produced frames use LF (\n) line endings as per the ESL protocol.
 * Content-Length values are computed exactly from the body bytes.
 *
 * @internal Test support only — not part of the public API.
 */
final class EslFixtureBuilder
{
    /**
     * Build an ESL frame from explicit headers and optional body.
     *
     * @param array<string, string> $headers
     */
    public static function frame(array $headers, string $body = ''): string
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\n";
        }

        if ($body !== '') {
            $headerBlock .= "Content-Length: " . strlen($body) . "\n";
        }

        return $headerBlock . "\n" . $body;
    }

    /**
     * The auth/request frame sent by FreeSWITCH to the connecting client.
     */
    public static function authRequest(): string
    {
        return "Content-Type: auth/request\n\n";
    }

    /**
     * A successful auth reply.
     */
    public static function authAccepted(): string
    {
        return "Content-Type: command/reply\nReply-Text: +OK accepted\n\n";
    }

    /**
     * A failed auth reply.
     */
    public static function authRejected(): string
    {
        return "Content-Type: command/reply\nReply-Text: -ERR invalid\n\n";
    }

    /**
     * A generic successful command reply.
     */
    public static function commandReplyOk(string $replyText = '+OK'): string
    {
        return "Content-Type: command/reply\nReply-Text: {$replyText}\n\n";
    }

    /**
     * A generic error command reply.
     */
    public static function commandReplyErr(string $reason = 'command not found'): string
    {
        return "Content-Type: command/reply\nReply-Text: -ERR {$reason}\n\n";
    }

    /**
     * A bgapi acceptance reply with a Job-UUID.
     */
    public static function bgapiAccepted(string $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38'): string
    {
        return "Content-Type: command/reply\nReply-Text: +OK Job-UUID: {$jobUuid}\n\n";
    }

    /**
     * An api/response frame with the given body.
     */
    public static function apiResponse(string $body): string
    {
        $len = strlen($body);
        return "Content-Type: api/response\nContent-Length: {$len}\n\n{$body}";
    }

    /**
     * A text/event-plain frame wrapping the given event data block.
     *
     * $eventData should be a complete event header block (Key: Value\n lines,
     * terminated by \n). Any inner body must already be included in $eventData.
     */
    public static function eventPlain(string $eventData): string
    {
        $len = strlen($eventData);
        return "Content-Type: text/event-plain\nContent-Length: {$len}\n\n{$eventData}";
    }

    /**
     * A text/event-json frame wrapping the given JSON payload.
     */
    public static function eventJson(string $json): string
    {
        $len = strlen($json);
        return "Content-Type: text/event-json\nContent-Length: {$len}\n\n{$json}";
    }

    /**
     * A text/event-xml frame wrapping the given XML payload.
     */
    public static function eventXml(string $xml): string
    {
        $len = strlen($xml);
        return "Content-Type: text/event-xml\nContent-Length: {$len}\n\n{$xml}";
    }

    /**
     * Build event data for a text/event-plain event (URL-encoded header values).
     *
     * @param array<string, string> $headers Header values should be pre-encoded or plain ASCII.
     * @param string $body Optional event body (appended after \n).
     */
    public static function eventData(array $headers, string $body = ''): string
    {
        $block = '';
        foreach ($headers as $name => $value) {
            $block .= "{$name}: {$value}\n";
        }

        if ($body !== '') {
            $block .= "Content-Length: " . strlen($body) . "\n";
        }

        $block .= "\n";

        if ($body !== '') {
            $block .= $body;
        }

        return $block;
    }

    /**
     * Build a deterministic JSON event payload.
     *
     * @param array<string, scalar> $headers
     */
    public static function eventJsonData(array $headers, string $body = ''): string
    {
        $payload = $headers;
        if ($body !== '') {
            $payload['_body'] = $body;
        }

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $json;
    }

    /**
     * Build a deterministic XML event payload.
     *
     * @param array<string, scalar> $headers
     */
    public static function eventXmlData(array $headers, string $body = ''): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><event><headers>';

        foreach ($headers as $name => $value) {
            $xml .= sprintf(
                '<%1$s>%2$s</%1$s>',
                $name,
                htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );
        }

        $xml .= '</headers>';

        if ($body !== '') {
            $xml .= sprintf(
                '<body>%s</body>',
                htmlspecialchars($body, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            );
        }

        $xml .= '</event>';

        return $xml;
    }

    /**
     * A complete CHANNEL_CREATE event frame.
     */
    public static function channelCreateEvent(
        string $uniqueId = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
        string $coreUuid = '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6',
    ): string {
        $eventData = self::eventData([
            'Event-Name'                => 'CHANNEL_CREATE',
            'Core-UUID'                 => $coreUuid,
            'FreeSWITCH-Hostname'       => 'pbx01',
            'FreeSWITCH-IPv4'           => '192.168.1.100',
            'Event-Date-Local'          => '2016-12-22%2010%3A30%3A00',
            'Event-Date-GMT'            => 'Thu%2C%2022%20Dec%202016%2015%3A30%3A00%20GMT',
            'Event-Date-Timestamp'      => '1482422200000000',
            'Event-Calling-File'        => 'switch_channel.c',
            'Event-Calling-Function'    => 'switch_channel_perform_set_state',
            'Event-Calling-Line-Number' => '1234',
            'Event-Sequence'            => '12345',
            'Channel-State'             => 'CS_INIT',
            'Unique-ID'                 => $uniqueId,
            'Call-Direction'            => 'inbound',
            'Channel-Name'              => 'sofia/internal/1001%40192.168.1.100',
            'Caller-Caller-ID-Name'     => 'User%201001',
            'Caller-Caller-ID-Number'   => '1001',
        ]);

        return self::eventPlain($eventData);
    }

    /**
     * A complete BACKGROUND_JOB event frame.
     */
    public static function backgroundJobEvent(
        string $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38',
        string $jobResult = "+OK\n",
        string $coreUuid = '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6',
    ): string {
        $eventData = self::eventData(
            headers: [
                'Event-Name'                => 'BACKGROUND_JOB',
                'Core-UUID'                 => $coreUuid,
                'FreeSWITCH-Hostname'       => 'pbx01',
                'FreeSWITCH-IPv4'           => '192.168.1.100',
                'Event-Date-Local'          => '2016-12-22%2010%3A30%3A01',
                'Event-Date-GMT'            => 'Thu%2C%2022%20Dec%202016%2015%3A30%3A01%20GMT',
                'Event-Date-Timestamp'      => '1482422201000000',
                'Event-Calling-File'        => 'mod_event_socket.c',
                'Event-Calling-Function'    => 'api_exec',
                'Event-Calling-Line-Number' => '2012',
                'Event-Sequence'            => '12346',
                'Job-UUID'                  => $jobUuid,
                'Job-Command'               => 'status',
            ],
            body: $jobResult,
        );

        return self::eventPlain($eventData);
    }

    /**
     * A complete CHANNEL_HANGUP event frame.
     */
    public static function hangupEvent(
        string $uniqueId = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
        string $hangupCause = 'NORMAL_CLEARING',
        string $coreUuid = '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6',
    ): string {
        $eventData = self::eventData([
            'Event-Name'                => 'CHANNEL_HANGUP',
            'Core-UUID'                 => $coreUuid,
            'FreeSWITCH-Hostname'       => 'pbx01',
            'FreeSWITCH-IPv4'           => '192.168.1.100',
            'Event-Date-Local'          => '2016-12-22%2010%3A30%3A05',
            'Event-Date-GMT'            => 'Thu%2C%2022%20Dec%202016%2015%3A30%3A05%20GMT',
            'Event-Date-Timestamp'      => '1482422205000000',
            'Event-Calling-File'        => 'switch_core_session.c',
            'Event-Calling-Function'    => 'switch_core_session_perform_destroy',
            'Event-Calling-Line-Number' => '567',
            'Event-Sequence'            => '12350',
            'Channel-State'             => 'CS_HANGUP',
            'Unique-ID'                 => $uniqueId,
            'Call-Direction'            => 'inbound',
            'Hangup-Cause'              => $hangupCause,
        ]);

        return self::eventPlain($eventData);
    }

    /**
     * A complete CHANNEL_BRIDGE or CHANNEL_UNBRIDGE event frame.
     */
    public static function bridgeEvent(
        string $eventName = 'CHANNEL_BRIDGE',
        string $uniqueId = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
        string $otherLegUniqueId = 'b4fbde13-9c33-45d7-a1e4-e6517eb8de91',
    ): string {
        $eventData = self::eventData([
            'Event-Name' => $eventName,
            'Core-UUID' => '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6',
            'Event-Sequence' => '12347',
            'Unique-ID' => $uniqueId,
            'Channel-Name' => 'sofia/internal/1001%40192.168.1.100',
            'Other-Leg-Unique-ID' => $otherLegUniqueId,
            'Other-Leg-Channel-Name' => 'sofia/internal/1002%40192.168.1.100',
        ]);

        return self::eventPlain($eventData);
    }

    /**
     * A complete PLAYBACK_START or PLAYBACK_STOP event frame.
     */
    public static function playbackEvent(
        string $eventName = 'PLAYBACK_START',
        string $uniqueId = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
        string $playbackUuid = 'c8dc43f6-5aa9-46b0-8ef2-14610d46a4d0',
        string $playbackFilePath = '/tmp/demo.wav',
    ): string {
        $eventData = self::eventData([
            'Event-Name' => $eventName,
            'Core-UUID' => '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6',
            'Event-Sequence' => '12348',
            'Unique-ID' => $uniqueId,
            'Channel-Name' => 'sofia/internal/1001%40192.168.1.100',
            'Playback-UUID' => $playbackUuid,
            'Playback-File-Path' => str_replace('@', '%40', $playbackFilePath),
        ]);

        return self::eventPlain($eventData);
    }

    /**
     * A disconnect notice frame.
     */
    public static function disconnectNotice(): string
    {
        $body = "Content-Type: text/disconnect-notice\nContent-Disposition: disconnect\n\n";

        return self::frame(
            headers: ['Content-Type' => 'text/disconnect-notice'],
            body: $body,
        );
    }
}
