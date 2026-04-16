<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Capabilities;

use Apntalk\EslCore\Capabilities\Capability;
use Apntalk\EslCore\Capabilities\CapabilityMap;
use Apntalk\EslCore\Capabilities\FeatureSupportLevel;
use PHPUnit\Framework\TestCase;

final class CapabilityMapTest extends TestCase
{
    public function test_declares_supported_core_readiness_surfaces_honestly(): void
    {
        $map = new CapabilityMap();

        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::Auth));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::ApiCommand));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::BgapiCommand));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::InboundDecodingFacade));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::ReplyParsing));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::EventPlainDecoding));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::EventJsonDecoding));
        $this->assertSame(FeatureSupportLevel::Provisional, $map->supportLevel(Capability::EventXmlDecoding));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::NormalizedEvents));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::CorrelationMetadata));
        $this->assertSame(FeatureSupportLevel::Provisional, $map->supportLevel(Capability::ReplayEnvelopeExport));
        $this->assertSame(FeatureSupportLevel::Provisional, $map->supportLevel(Capability::ReconstructionHookSupport));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::InMemoryTransport));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::SocketTransportConstruction));
        $this->assertSame(FeatureSupportLevel::Stable, $map->supportLevel(Capability::InboundConnectionBootstrap));
    }

    public function test_supports_returns_false_only_for_unsupported_capabilities(): void
    {
        $map = new CapabilityMap();

        foreach ($map->all() as $capability => $level) {
            $enum = Capability::from($capability);
            $this->assertSame($level !== FeatureSupportLevel::Unsupported, $map->supports($enum));
        }
    }

    public function test_declared_capability_set_contains_json_and_correlation_support(): void
    {
        $all = (new CapabilityMap())->all();

        $this->assertArrayHasKey(Capability::EventJsonDecoding->value, $all);
        $this->assertArrayHasKey(Capability::EventXmlDecoding->value, $all);
        $this->assertArrayHasKey(Capability::CorrelationMetadata->value, $all);
        $this->assertArrayHasKey(Capability::SocketTransportConstruction->value, $all);
        $this->assertArrayHasKey(Capability::InboundConnectionBootstrap->value, $all);
    }

    public function test_declared_capability_map_is_exhaustive_for_all_enum_cases(): void
    {
        $declared = array_keys((new CapabilityMap())->all());
        $expected = array_map(
            static fn(Capability $capability): string => $capability->value,
            Capability::cases(),
        );

        sort($declared);
        sort($expected);

        $this->assertSame($expected, $declared);
    }
}
