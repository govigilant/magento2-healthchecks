<?php

namespace Vigilant\MagentoHealthchecks\Checks;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class SearchEngineCheck extends Check
{
    private const ENGINE_ELASTICSEARCH = 'elasticsearch';
    private const ENGINE_OPENSEARCH = 'opensearch';
    private const DEFAULT_TIMEOUT_SECONDS = 5;

    protected string $type = 'search_engine';

    public function __construct(
        protected readonly ScopeConfigInterface $scopeConfig,
        protected readonly CurlFactory $curlFactory
    ) {}

    public function run(): ResultData
    {
        $engine = $this->getEngine();
        $host = $this->getConfigValue($this->buildPath($engine, 'server_hostname'));
        $port = $this->getConfigValue($this->buildPath($engine, 'server_port'));
        $scheme = $this->getConfigValue($this->buildPath($engine, 'server_protocol'));
        $username = $this->getConfigValue($this->buildPath($engine, 'username'));
        $password = $this->getConfigValue($this->buildPath($engine, 'password'));

        if ($host === null || $port === null || $scheme === null) {
            return ResultData::make([
                'type' => $this->type(),
                'key' => $engine,
                'status' => Status::Warning,
                'message' => 'Search engine configuration is incomplete; host/port/scheme missing.',
            ]);
        }

        $port = (int) $port;
        if ($port <= 0) {
            return ResultData::make([
                'type' => $this->type(),
                'key' => $engine,
                'status' => Status::Warning,
                'message' => 'Search engine port must be greater than zero.',
            ]);
        }

        $scheme = strtolower($scheme);
        $url = sprintf('%s://%s:%d/_cluster/health', $scheme, $host, $port);

        try {
            $client = $this->curlFactory->create();
            $client->addHeader('Accept', 'application/json');
            $client->setOptions([
                CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT_SECONDS,
            ]);

            if ($username && $password) {
                $client->setCredentials($username, $password);
            }

            $client->get($url);
            $statusCode = $client->getStatus();

            if ($statusCode >= 200 && $statusCode < 300) {
                $body = $client->getBody();
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $clusterStatus = $data['status'] ?? 'unknown';

                return ResultData::make([
                    'type' => $this->type(),
                    'key' => $engine,
                    'status' => in_array($clusterStatus, ['green', 'yellow'], true) ? Status::Healthy : Status::Unhealthy,
                    'message' => sprintf('Search engine responded with cluster status "%s".', $clusterStatus),
                ]);
            }

            return ResultData::make([
                'type' => $this->type(),
                'key' => $engine,
                'status' => Status::Unhealthy,
                'message' => sprintf('Search engine responded with HTTP %d.', $statusCode),
            ]);
        } catch (Throwable $exception) {
            return ResultData::make([
                'type' => $this->type(),
                'key' => $engine,
                'status' => Status::Unhealthy,
                'message' => 'Failed to query search engine: ' . $exception->getMessage(),
            ]);
        }
    }

    public function available(): bool
    {
        $engine = strtolower($this->getEngine());

        if ($engine === '') {
            return false;
        }

        return str_starts_with($engine, static::ENGINE_ELASTICSEARCH) || $engine === static::ENGINE_OPENSEARCH;
    }

    private function getEngine(): string
    {
        return (string) ($this->getConfigValue('catalog/search/engine') ?? '');
    }

    private function buildPath(string $engine, string $suffix): string
    {
        return sprintf('catalog/search/%s_%s', $engine, $suffix);
    }

    private function getConfigValue(string $path): ?string
    {
        $value = $this->scopeConfig->getValue($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        return $value !== null ? (string) $value : null;
    }
}
