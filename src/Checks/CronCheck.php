<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\CacheInterface;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use Vigilant\MagentoHealthchecks\Cron\CronHeartbeat;
use Vigilant\MagentoHealthchecks\Model\Config;

class CronCheck extends Check
{
    protected string $type = 'cron';

    public function __construct(
        protected readonly CacheInterface $cache,
        protected readonly Config $config
    ) {}

    public function run(): ResultData
    {
        /** @var string|false $lastRun */
        $lastRun = $this->cache->load(CronHeartbeat::CACHE_KEY);

        if (! is_string($lastRun) || $lastRun === '') {
            return ResultData::make([
                'type' => $this->type(),
                'status' => Status::Unhealthy,
                'message' => 'Cron has never run.',
            ]);
        }

        $lastTimestamp = (int) $lastRun;
        $elapsed = time() - $lastTimestamp;
        $maxDelay = max(1, $this->config->getCronMaxDelay());

        if ($elapsed > $maxDelay) {
            return ResultData::make([
                'type' => $this->type(),
                'status' => Status::Unhealthy,
                'message' => sprintf('Last cron run is %s seconds ago', $elapsed),
            ]);
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => Status::Healthy,
            'message' => 'Cron is running',
        ]);
    }

    public function available(): bool
    {
        return true;
    }
}
