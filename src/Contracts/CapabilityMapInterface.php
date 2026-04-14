<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Capabilities\Capability;
use Apntalk\EslCore\Capabilities\FeatureSupportLevel;

/**
 * Contract for capability maps that declare what this package supports.
 *
 * Capabilities reflect actual tested behavior, not aspirations.
 * Do not declare a capability until it is implemented and test-covered.
 */
interface CapabilityMapInterface
{
    /**
     * Get the support level for a named capability.
     *
     * Returns FeatureSupportLevel::Unsupported if the capability is not declared.
     */
    public function supportLevel(Capability $capability): FeatureSupportLevel;

    /**
     * Whether the given capability is supported at any level.
     */
    public function supports(Capability $capability): bool;

    /**
     * All declared capabilities and their support levels.
     *
     * @return array<string, FeatureSupportLevel>
     */
    public function all(): array;
}
