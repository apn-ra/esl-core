<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Exceptions;

use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Exceptions\UnsupportedContentTypeException;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class ErrorTaxonomyTest extends TestCase
{
    public function test_malformed_header_line_uses_malformed_frame_exception(): void
    {
        $parser = new FrameParser();

        $this->expectException(MalformedFrameException::class);

        $parser->feed("Content-Type auth/request\n\n");
    }

    public function test_end_of_input_with_partial_body_uses_truncated_frame_exception(): void
    {
        $parser = new FrameParser();
        $parser->feed("Content-Type: api/response\nContent-Length: 5\n\nabc");

        $this->expectException(TruncatedFrameException::class);

        $parser->finish();
    }

    public function test_event_parser_unsupported_content_type_uses_specific_exception(): void
    {
        $frame = new Frame(HeaderBag::fromHeaderBlock("Content-Type: command/reply"), '');

        $this->expectException(UnsupportedContentTypeException::class);

        (new EventParser())->parse($frame);
    }

    public function test_bgapi_reply_requires_non_empty_job_uuid(): void
    {
        $frame = new Frame(
            HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK Job-UUID: "),
            ''
        );

        $this->expectException(UnexpectedReplyException::class);

        BgapiAcceptedReply::fromFrame($frame);
    }

    public function test_api_reply_rejects_non_api_frame(): void
    {
        $frame = new Frame(HeaderBag::fromHeaderBlock("Content-Type: command/reply"), '');

        $this->expectException(UnexpectedReplyException::class);

        ApiReply::fromFrame($frame);
    }

    public function test_closed_transport_throws_transport_exception(): void
    {
        $transport = new InMemoryTransport();
        $transport->close();

        $this->expectException(TransportException::class);

        $transport->read(1);
    }
}
