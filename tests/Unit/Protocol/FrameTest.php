<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Protocol;

use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use PHPUnit\Framework\TestCase;

final class FrameTest extends TestCase
{
    public function test_content_type_from_header(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: auth/request");
        $frame   = new Frame($headers, '');

        $this->assertSame('auth/request', $frame->contentType());
    }

    public function test_content_type_null_when_absent(): void
    {
        $frame = new Frame(HeaderBag::fromHeaderBlock(''), '');

        $this->assertNull($frame->contentType());
    }

    public function test_content_length_parsed_as_int(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: api/response\nContent-Length: 42");
        $frame   = new Frame($headers, str_repeat('x', 42));

        $this->assertSame(42, $frame->contentLength());
    }

    public function test_content_length_null_when_absent(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: command/reply");
        $frame   = new Frame($headers, '');

        $this->assertNull($frame->contentLength());
    }

    public function test_reply_text_from_header(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK accepted");
        $frame   = new Frame($headers, '');

        $this->assertSame('+OK accepted', $frame->replyText());
    }

    public function test_reply_text_null_when_absent(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: api/response\nContent-Length: 0");
        $frame   = new Frame($headers, '');

        $this->assertNull($frame->replyText());
    }

    public function test_has_body_true_when_body_nonempty(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: api/response\nContent-Length: 3");
        $frame   = new Frame($headers, '+OK');

        $this->assertTrue($frame->hasBody());
    }

    public function test_has_body_false_for_empty_body(): void
    {
        $headers = HeaderBag::fromHeaderBlock("Content-Type: command/reply");
        $frame   = new Frame($headers, '');

        $this->assertFalse($frame->hasBody());
    }
}
