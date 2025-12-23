<?php

namespace Vigilant\MagentoHealthchecks\Model;

use Vigilant\HealthChecksBase\BuildResponse;
use Vigilant\MagentoHealthchecks\Api\HealthInterface;
use Vigilant\MagentoHealthchecks\HealthCheckRegistry;

class Health implements HealthInterface
{
    public function __construct(
        protected readonly HealthCheckRegistry $registry,
        protected readonly BuildResponse $builder
    ) {}

    public function get(): array
    {
        return $this->builder->build(
            $this->registry->getChecks(),
            $this->registry->getMetrics()
        );
    }
}
