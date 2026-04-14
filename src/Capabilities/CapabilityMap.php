<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Capabilities;

use Apntalk\EslCore\Contracts\CapabilityMapInterface;

/**
 * Declares the supported capabilities of this package version.
 *
 * Capabilities are backed by real tests and documentation.
 * This map should be updated when capabilities are added or promoted.
 *
 * @api
 */
final class CapabilityMap implements CapabilityMapInterface
{
    /**
     * The declared capability set for this package baseline.
     *
     * @var array<string, FeatureSupportLevel>
     */
    private static array $capabilities = [
        'auth'                          => FeatureSupportLevel::Stable,
        'api-command'                   => FeatureSupportLevel::Stable,
        'bgapi-command'                 => FeatureSupportLevel::Stable,
        'inbound-decoding-facade'       => FeatureSupportLevel::Stable,
        'reply-parsing'                 => FeatureSupportLevel::Stable,
        'event-subscription'            => FeatureSupportLevel::Stable,
        'event-plain-decoding'          => FeatureSupportLevel::Stable,
        'event-json-decoding'           => FeatureSupportLevel::Stable,
        'normalized-events'             => FeatureSupportLevel::Stable,
        'correlation-metadata'          => FeatureSupportLevel::Stable,
        'replay-envelope-export'        => FeatureSupportLevel::Provisional,
        'reconstruction-hook-support'   => FeatureSupportLevel::Provisional,
        'in-memory-transport'           => FeatureSupportLevel::Stable,
        'fixture-replay-compatibility'  => FeatureSupportLevel::Provisional,
    ];

    public function supportLevel(Capability $capability): FeatureSupportLevel
    {
        return self::$capabilities[$capability->value] ?? FeatureSupportLevel::Unsupported;
    }

    public function supports(Capability $capability): bool
    {
        $level = $this->supportLevel($capability);
        return $level !== FeatureSupportLevel::Unsupported;
    }

    public function all(): array
    {
        return self::$capabilities;
    }
}
