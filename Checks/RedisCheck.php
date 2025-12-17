<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\DeploymentConfig;
use RuntimeException;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class RedisCheck extends Check
{
    protected string $type = 'redis_connection';

    public function __construct(
        protected readonly DeploymentConfig $deploymentConfig
    ) {}

    public function run(): ResultData
    {
        $connections = $this->getRedisConnectionConfigs();

        foreach ($connections as $name => $config) {
            try {
                $this->pingRedis($config);
            } catch (Throwable $exception) {
                return ResultData::make([
                    'type' => $this->type(),
                    'key' => $name,
                    'status' => Status::Unhealthy,
                    'message' => sprintf("Failed to connect to Redis '%s': %s", $name, $exception->getMessage()),
                ]);
            }
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => Status::Healthy,
            'message' => 'Redis connections are healthy.',
            'data' => [
                'connections' => array_keys($connections),
            ],
        ]);
    }

    public function available(): bool
    {
        return $this->getRedisConnectionConfigs() !== [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getRedisConnectionConfigs(): array
    {
        $connections = [];

        $cacheFrontends = $this->deploymentConfig->get('cache/frontend');
        if (is_array($cacheFrontends)) {
            foreach ($cacheFrontends as $name => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $backend = $config['backend'] ?? '';
                if (is_string($backend) && stripos($backend, 'redis') !== false) {
                    $options = $config['backend_options'] ?? [];
                    if (is_array($options) && $options !== []) {
                        $connections['cache_' . $name] = $options;
                    }
                }
            }
        }

        $sessionConfig = $this->deploymentConfig->get('session');
        if (
            is_array($sessionConfig)
            && ($sessionConfig['save'] ?? null) === 'redis'
            && isset($sessionConfig['redis'])
            && is_array($sessionConfig['redis'])
        ) {
            $connections['session'] = $sessionConfig['redis'];
        }

        return $connections;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function pingRedis(array $config): void
    {
        $hostValue = $config['server'] ?? $config['host'] ?? null;
        $portValue = $config['port'] ?? null;

        if (! is_string($hostValue) || $hostValue === '') {
            throw new RuntimeException('Redis host is not configured.');
        }

        if ($portValue === null || (int) $portValue <= 0) {
            throw new RuntimeException('Redis port is not configured.');
        }

        $host = (string) $hostValue;
        $port = (int) $portValue;
        $timeoutValue = $config['timeout'] ?? null;
        $timeout = $timeoutValue !== null ? (float) $timeoutValue : 2.5;
        $persistent = (string) ($config['persistent'] ?? $config['persistent_identifier'] ?? '');
        $databaseValue = $config['database'] ?? $config['db'] ?? 0;
        $database = (int) $databaseValue;
        $password = $config['password'] ?? null;

        if (class_exists(\Redis::class)) {
            $client = new \Redis();

            if ($persistent !== '') {
                $client->pconnect($host, $port, $timeout, $persistent);
            } else {
                $client->connect($host, $port, $timeout);
            }

            if (is_string($password) && $password !== '') {
                $client->auth($password);
            }

            if ($database > 0) {
                $client->select($database);
            }

            $response = $client->ping();
            $this->assertPongResponse($response);
            $client->close();

            return;
        }

        if (class_exists(\Credis_Client::class)) {
            $client = new \Credis_Client($host, $port, $timeout, $persistent, $database);

            if (is_string($password) && $password !== '') {
                $client->auth($password);
            }

            $client->connect();
            $response = $client->ping();
            $this->assertPongResponse($response);
            $client->close();

            return;
        }

        throw new RuntimeException('Redis extensions are not available.');
    }

    private function assertPongResponse(mixed $response): void
    {
        if ($response === true) {
            $response = 'PONG';
        }

        if (! is_string($response) || stripos($response, 'PONG') === false) {
            throw new RuntimeException('Redis ping response did not contain PONG.');
        }
    }
}
