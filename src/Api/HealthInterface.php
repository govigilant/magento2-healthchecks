<?php

namespace Vigilant\MagentoHealthchecks\Api;

interface HealthInterface
{
    /**
     * Fetch the current health summary.
     *
     * @return array<string, mixed>
     */
    public function get(): array;
}
