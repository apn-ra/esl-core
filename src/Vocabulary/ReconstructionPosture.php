<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Declares how much reconstruction help a captured truth surface needs.
 *
 * @api
 */
enum ReconstructionPosture: string
{
    case Native = 'native';
    case HookRequired = 'hook-required';
    case Partial = 'partial';
    case Unsupported = 'unsupported';
    case Provisional = 'provisional';
}
