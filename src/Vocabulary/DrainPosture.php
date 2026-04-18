<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Drain posture vocabulary without owning drain execution.
 *
 * @api
 */
enum DrainPosture: string
{
    case NotDraining = 'not-draining';
    case Requested = 'requested';
    case Draining = 'draining';
    case Drained = 'drained';
    case Interrupted = 'interrupted';
    case Unknown = 'unknown';
}
