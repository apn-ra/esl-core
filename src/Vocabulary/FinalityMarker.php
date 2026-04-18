<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Terminal-publication finality vocabulary.
 *
 * @api
 */
enum FinalityMarker: string
{
    case Final = 'final';
    case NonFinal = 'non-final';
    case ProvisionalFinal = 'provisional-final';
    case Ambiguous = 'ambiguous';
}
