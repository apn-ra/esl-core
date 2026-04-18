<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Replay continuity vocabulary for reconstruction-oriented consumers.
 *
 * @api
 */
enum ReplayContinuity: string
{
    case Continuous = 'continuous';
    case GapDetected = 'gap-detected';
    case Reconstructed = 'reconstructed';
    case Partial = 'partial';
    case Ambiguous = 'ambiguous';
    case Unknown = 'unknown';
}
