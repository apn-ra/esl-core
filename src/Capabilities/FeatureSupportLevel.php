<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Capabilities;

/**
 * Support level for a declared capability.
 *
 * @api
 */
enum FeatureSupportLevel: string
{
    /** The capability is not implemented. */
    case Unsupported = 'unsupported';

    /** The capability is implemented but provisional — signatures may change. */
    case Provisional = 'provisional';

    /** The capability is implemented and stable. */
    case Stable = 'stable';
}
