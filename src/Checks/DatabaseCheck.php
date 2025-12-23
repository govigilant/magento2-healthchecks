<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\ResourceConnection;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class DatabaseCheck extends Check
{
    protected string $type = 'database_connection';

    public function __construct(
        protected readonly ResourceConnection $resourceConnection
    ) {}

    public function run(): ResultData
    {
        try {
            $this->resourceConnection->getConnection()->fetchOne('SELECT 1');
            $isHealthy = true;
            $message = 'Database connection is healthy.';
        } catch (Throwable $exception) {
            $isHealthy = false;
            $message = 'Failed to connect to the database: ' . $exception->getMessage();
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => $isHealthy ? Status::Healthy : Status::Unhealthy,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        try {
            $this->resourceConnection->getConnection();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
