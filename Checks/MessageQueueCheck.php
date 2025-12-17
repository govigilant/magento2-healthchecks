<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class MessageQueueCheck extends Check
{
    protected string $type = 'message_queue';
    private const FAILED_QUEUE_STATUSES = [3];

    public function __construct(
        protected readonly DeploymentConfig $deploymentConfig,
        protected readonly ResourceConnection $resourceConnection,
        protected readonly LoggerInterface $logger
    ) {}

    public function run(): ResultData
    {
        $amqpConnections = $this->getAmqpConnections();

        if ($amqpConnections !== []) {
            try {
                foreach ($amqpConnections as $name => $config) {
                    $this->assertAmqpConnection($config);
                }

                return ResultData::make([
                    'type' => $this->type(),
                    'key' => 'amqp',
                    'status' => Status::Healthy,
                    'message' => 'AMQP connections are healthy.',
                    'data' => [
                        'connections' => array_keys($amqpConnections),
                    ],
                ]);
            } catch (Throwable $exception) {
                $this->logger->error(
                    'Failed to connect to one or more AMQP connections.',
                    [
                        'connections' => array_keys($amqpConnections),
                        'exception' => $exception,
                    ]
                );

                return ResultData::make([
                    'type' => $this->type(),
                    'key' => 'amqp',
                    'status' => Status::Unhealthy,
                    'message' => 'Failed to connect to AMQP. See logs for details.',
                ]);
            }
        }

        return $this->checkDatabaseQueue();
    }

    public function available(): bool
    {
        if ($this->getAmqpConnections() !== []) {
            return true;
        }

        try {
            $this->resourceConnection->getConnection();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getAmqpConnections(): array
    {
        $connections = [];
        $queueConfig = $this->deploymentConfig->get('queue');

        if (! is_array($queueConfig)) {
            return $connections;
        }

        if (isset($queueConfig['amqp']) && is_array($queueConfig['amqp'])) {
            $connections['default'] = $queueConfig['amqp'];
        }

        if (isset($queueConfig['connections']) && is_array($queueConfig['connections'])) {
            foreach ($queueConfig['connections'] as $name => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $type = $config['type'] ?? $config['connection'] ?? null;
                $looksLikeAmqp = isset($config['host']) || isset($config['hostname']);

                if ($type === 'amqp' || ($config['driver'] ?? null) === 'amqp' || $looksLikeAmqp) {
                    $connections[(string) $name] = $config;
                }
            }
        }

        return $connections;
    }

    private function checkDatabaseQueue(): ResultData
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('queue_message');

            $connection->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', $tableName));

            $failedMessages = $this->getFailedMessagesCount($connection);

            $payload = [
                'type' => $this->type(),
                'key' => 'database',
                'status' => Status::Healthy,
                'message' => 'Database queue tables are reachable.',
            ];

            if ($failedMessages !== null) {
                $payload['data'] = [
                    'failed_messages' => $failedMessages,
                ];
            }

            return ResultData::make($payload);
        } catch (Throwable $exception) {
            return ResultData::make([
                'type' => $this->type(),
                'key' => 'database',
                'status' => Status::Unhealthy,
                'message' => 'Failed to query queue tables: ' . $exception->getMessage(),
            ]);
        }
    }

    private function getFailedMessagesCount(AdapterInterface $connection): ?int
    {
        try {
            $statuses = self::FAILED_QUEUE_STATUSES;

            if ($statuses === []) {
                return null;
            }

            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $statusTable = $connection->getTableName('queue_message_status');
            $result = $connection->fetchOne(
                sprintf('SELECT COUNT(*) FROM %s WHERE status IN (%s)', $statusTable, $placeholders),
                $statuses
            );

            return is_numeric($result) ? (int) $result : 0;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertAmqpConnection(array $config): void
    {
        $host = (string) ($config['host'] ?? $config['hostname'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 5672);
        $user = (string) ($config['user'] ?? $config['username'] ?? 'guest');
        $password = (string) ($config['password'] ?? 'guest');
        $virtualHost = (string) ($config['virtualhost'] ?? $config['vhost'] ?? '/');
        $ssl = (bool) ($config['ssl'] ?? $config['use_ssl'] ?? false);
        $timeout = (float) ($config['connection_timeout'] ?? $config['connect_options']['connection_timeout'] ?? 3.0);

        if (class_exists(\PhpAmqpLib\Connection\AMQPStreamConnection::class)) {
            if ($ssl && class_exists(\PhpAmqpLib\Connection\AMQPSSLConnection::class)) {
                $connection = new \PhpAmqpLib\Connection\AMQPSSLConnection(
                    $host,
                    $port,
                    $user,
                    $password,
                    $virtualHost,
                    []
                );
            } else {
                $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                    $host,
                    $port,
                    $user,
                    $password,
                    $virtualHost,
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    $timeout
                );
            }

            $connection->close();

            return;
        }

        if (class_exists(\AMQPConnection::class)) {
            $connection = new \AMQPConnection([
                'host' => $host,
                'port' => $port,
                'login' => $user,
                'password' => $password,
                'vhost' => $virtualHost,
                'read_timeout' => $timeout,
            ]);

            if (! $connection->connect()) {
                throw new RuntimeException('Failed to connect using ext-amqp connection.');
            }

            $connection->disconnect();

            return;
        }

        throw new RuntimeException('No AMQP client available to perform connectivity check.');
    }
}
