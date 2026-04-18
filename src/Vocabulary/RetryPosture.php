<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Retry posture vocabulary without owning retry scheduling.
 *
 * @api
 */
enum RetryPosture: string
{
    case NotRetryable = 'not-retryable';
    case Retryable = 'retryable';
    case Retrying = 'retrying';
    case Exhausted = 'exhausted';
    case Ambiguous = 'ambiguous';
    case Unknown = 'unknown';
}
