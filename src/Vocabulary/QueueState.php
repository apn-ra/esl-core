<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Queue-state vocabulary shared by downstream runtime packages.
 *
 * @api
 */
enum QueueState: string
{
    case NotQueued = 'not-queued';
    case Queued = 'queued';
    case InFlight = 'in-flight';
    case Draining = 'draining';
    case Drained = 'drained';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
