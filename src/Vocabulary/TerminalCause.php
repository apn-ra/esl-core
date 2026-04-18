<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Terminal cause vocabulary for protocol/core publication truth.
 *
 * @api
 */
enum TerminalCause: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed-out';
    case Disconnected = 'disconnected';
    case Hangup = 'hangup';
    case Unknown = 'unknown';
}
