<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Confidence/state marker for lifecycle semantic observations.
 *
 * @api
 */
enum LifecycleSemanticState: string
{
    case Confirmed = 'confirmed';
    case Provisional = 'provisional';
    case Ambiguous = 'ambiguous';
    case BoundedVariance = 'bounded-variance';
    case Unknown = 'unknown';
}
