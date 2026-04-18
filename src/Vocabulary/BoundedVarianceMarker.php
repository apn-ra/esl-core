<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Truth marker for values that are intentionally non-binary.
 *
 * @api
 */
enum BoundedVarianceMarker: string
{
    case None = 'none';
    case Ambiguous = 'ambiguous';
    case Provisional = 'provisional';
    case BoundedVariance = 'bounded-variance';
    case Unknown = 'unknown';
}
