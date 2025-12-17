<?php

namespace Vigilant\MagentoHealthchecks;

use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Checks\Metric;

class HealthCheckRegistry
{
    /**
     * @param array<Check> $checks
     * @param array<Metric> $metrics
     */
    public function __construct(
        protected readonly array $checks = [],
        protected readonly array $metrics = []
    ) {}

    /**
     * @return array<Check>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * @return array<Metric>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
