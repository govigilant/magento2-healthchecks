<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\CacheInterface;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use Vigilant\MagentoHealthchecks\Model\Config;

class CacheCheck extends Check
{
    protected string $type = 'cache_store';

    public function __construct(
        protected readonly CacheInterface $cache,
        protected readonly Config $config
    ) {}

    public function run(): ResultData
    {
        try {
            $key = 'vigilant_healthcheck_' . bin2hex(random_bytes(8));
            $value = bin2hex(random_bytes(12));

            $ttl = max(1, $this->config->getCacheProbeTtl());
            $this->cache->save($value, $key, [], $ttl);
            $retrieved = $this->cache->load($key);
            $this->cache->remove($key);

            $isHealthy = $retrieved === $value;
            $message = $isHealthy
                ? 'Cache storage is healthy.'
                : 'Cache storage is not working correctly.';
        } catch (Throwable $exception) {
            $isHealthy = false;
            $message = 'Failed to use the cache storage: ' . $exception->getMessage();
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => $isHealthy ? Status::Healthy : Status::Unhealthy,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        return true;
    }
}
