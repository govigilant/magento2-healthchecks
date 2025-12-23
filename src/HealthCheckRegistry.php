<?php

namespace Vigilant\MagentoHealthchecks;

use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\MagentoHealthchecks\Model\Config;

class HealthCheckRegistry
{
    /**
     * @param array<Check> $checks
     * @param array<Metric> $metrics
     */
    public function __construct(
        private readonly Config $config,
        protected readonly array $checks = [],
        protected readonly array $metrics = []
    ) {}

    /**
     * @return array<Check>
     */
    public function getChecks(): array
    {
        $disabled = $this->config->getDisabledChecks();

        if ($disabled === []) {
            return $this->checks;
        }

        return array_filter(
            $this->checks,
            static fn (Check $check, string $name): bool => ! in_array($name, $disabled, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<Metric>
     */
    public function getMetrics(): array
    {
        $disabled = $this->config->getDisabledMetrics();

        if ($disabled === []) {
            return $this->metrics;
        }

        return array_filter(
            $this->metrics,
            static fn (Metric $metric, string $name): bool => ! in_array($name, $disabled, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
