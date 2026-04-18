<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Lifecycle semantic transition vocabulary for downstream projection.
 *
 * @api
 */
enum LifecycleTransition: string
{
    case Transfer = 'transfer';
    case Bridge = 'bridge';
    case Hold = 'hold';
    case Resume = 'resume';
    case Terminal = 'terminal';
}
