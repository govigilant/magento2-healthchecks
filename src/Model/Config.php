<?php

namespace Vigilant\MagentoHealthchecks\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_CRON_HEARTBEAT_SCHEDULE = 'vigilant_healthchecks/cron/heartbeat_schedule';
    private const XML_PATH_CRON_HEARTBEAT_TTL = 'vigilant_healthchecks/cron/heartbeat_ttl';
    private const XML_PATH_CRON_MAX_DELAY = 'vigilant_healthchecks/cron/max_delay';

    private const XML_PATH_CACHE_PROBE_TTL = 'vigilant_healthchecks/cache/probe_ttl';

    private const XML_PATH_DISABLED_CHECKS = 'vigilant_healthchecks/controls/disabled_checks';
    private const XML_PATH_DISABLED_METRICS = 'vigilant_healthchecks/controls/disabled_metrics';


    public function __construct(private readonly ScopeConfigInterface $scopeConfig) {}

    public function getHeartbeatSchedule(): string
    {
        return $this->getStringValue(self::XML_PATH_CRON_HEARTBEAT_SCHEDULE, '* * * * *');
    }

    public function getHeartbeatTtl(): int
    {
        return max(1, (int) $this->getStringValue(self::XML_PATH_CRON_HEARTBEAT_TTL, '300'));
    }

    public function getCronMaxDelay(): int
    {
        return max(1, (int) $this->getStringValue(self::XML_PATH_CRON_MAX_DELAY, '120'));
    }

    public function getCacheProbeTtl(): int
    {
        return max(1, (int) $this->getStringValue(self::XML_PATH_CACHE_PROBE_TTL, '60'));
    }

    /**
     * @return array<string>
     */
    public function getDisabledChecks(): array
    {
        return $this->getArrayValue(self::XML_PATH_DISABLED_CHECKS);
    }

    /**
     * @return array<string>
     */
    public function getDisabledMetrics(): array
    {
        return $this->getArrayValue(self::XML_PATH_DISABLED_METRICS);
    }


    private function getStringValue(string $path, string $default = ''): string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @return array<string>
     */
    private function getArrayValue(string $path): array
    {
        $value = $this->getStringValue($path, '');

        if ($value === '') {
            return [];
        }

        $values = array_map('trim', explode(',', $value));

        return array_values(array_filter($values, static fn (string $item): bool => $item !== ''));
    }
}
