<?php

namespace Vigilant\MagentoHealthchecks\Cron;

use Magento\Framework\App\CacheInterface;
use Vigilant\MagentoHealthchecks\Model\Config;

class CronHeartbeat
{
    public const CACHE_KEY = 'vigilant_cron_heartbeat';

    public function __construct(
        protected readonly CacheInterface $cache,
        protected readonly Config $config
    ) {}

    public function execute(): void
    {
        $ttl = max(1, $this->config->getHeartbeatTtl());
        $this->cache->save((string) time(), static::CACHE_KEY, [], $ttl);
    }
}
