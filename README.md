<a href="https://github.com/govigilant/vigilant" title="Vigilant">
    <img src="./art/banner.png" alt="Banner">
</a>

# Vigilant Magento Healthchecks

<p>
    <a href="https://github.com/govigilant/magento-healthchecks"><img src="https://img.shields.io/github/actions/workflow/status/govigilant/laravel-healthchecks/analyse.yml?label=analysis&style=flat-square" alt="Analysis"></a>
    <a href="https://packagist.org/packages/govigilant/magento-healthchecks"><img src="https://img.shields.io/packagist/dt/govigilant/laravel-healthchecks?color=blue&style=flat-square" alt="Total downloads"></a>
</p>

A module that adds healthchecks to any Magento 2 application and integrates seamlessly with [Vigilant](https://github.com/govigilant/vigilant).

## Features

This package providers an API endpoint to check the health of your Magento 2 store. It returns two types of checks, health checks and metrics.
Healthchecks are checks that indicate whether a specific part of your application is functioning correctly, while metrics provide numeric values that give insights on health over time. [Vigilant](https://github.com/govigilant/vigilant) can use these metrics to notify you of spikes or quickly increasing metrics.

## Installation

Install the package via Composer:

```bash
composer require govigilant/magento2-healthchecks
```

After installation, enable the module and run the usual Magento maintenance commands:

```bash
bin/magento module:enable Vigilant_MagentoHealthchecks
bin/magento setup:upgrade
```

## Configuration

Create an integration with access to the `Health Endpoint` resource in your Magento admin panel and use the access token as bearer token to access the health endpoint.

## Usage

### Adding Custom Checks and Metrics

Create checks by extending `\Vigilant\HealthChecksBase\Checks\Check` and implementing the `run()` method to return a `ResultData` instance containing the type, status, message, and any optional data payload. Metrics extend `\Vigilant\HealthChecksBase\Checks\Metric` and implement `measure()` to return a `MetricData` object with a numeric value and unit. Register your implementations by wiring them into `Vigilant\MagentoHealthchecks\HealthCheckRegistry` inside your module's `etc/di.xml`:

```xml
<type name="Vigilant\MagentoHealthchecks\HealthCheckRegistry">
    <arguments>
        <argument name="checks" xsi:type="array">
            <item name="my_custom_check" xsi:type="object">Vendor\Module\Checks\MyCustomCheck</item>
        </argument>
        <argument name="metrics" xsi:type="array">
            <item name="my_custom_metric" xsi:type="object">Vendor\Module\Checks\Metrics\MyCustomMetric</item>
        </argument>
    </arguments>
</type>
```

### Accessing the Health Endpoint

Once installed, the health check endpoint is available with your configured bearer token at:

```
POST /rest/V1/vigilant/health
```

Or using `curl`:

```
curl -X POST "YOUR_URL_HERE/rest/V1/vigilant/health" \
  -H "Authorization: Bearer YOUR_BEARER_TOKEN" \
  -H "Content-Type: application/json"
```

## Available Checks

| Check | Description |
|-------|-------------|
| **CacheCheck** | Performs a write/read/delete probe against the configured cache backend to verify it stores data correctly. |
| **SearchEngineCheck** | Connects to the configured Elasticsearch/OpenSearch cluster and reports its cluster health status. |
| **DatabaseCheck** | Runs a lightweight query to ensure the primary Magento database connection is reachable. |
| **MessageQueueCheck** | Validates AMQP connections when configured or falls back to inspecting the database queue tables for failures. |
| **CronCheck** | Ensures the cron heartbeat has been updated within the configured time window, indicating cron is running. |
| **RedisCheck** | Discovers Redis-backed caches/sessions from deployment config and pings each server to confirm connectivity. |
| **DebugModeCheck** | Warns when the application is running in developer mode to avoid leaking sensitive debug output in production. |

## Available Metrics

| Metric | Description |
|--------|-------------|
| **MemoryUsageMetric** | Reports overall system memory usage percentage to track saturation. |
| **CpuLoadMetric** | Captures the current CPU load average to highlight processor spikes. |
| **DiskUsageMetric** | Measures disk space consumption of the configured filesystem. |
| **IndexerMetric (invalid)** | Counts indexers currently marked as invalid and awaiting reindex. |
| **IndexerMetric (working)** | Counts indexers that are actively running. |
| **IndexerMetric (valid)** | Counts indexers that are up-to-date and valid. |
| **IndexerWorkingMinutesMetric** | Tracks the longest-running indexer by reporting its time in the working state in minutes. |

## Development Environment

A ready-to-use Docker-based development environment lives in `devenv/`.
Ensure Docker is running, then start the stack: `docker compose -f devenv/docker-compose.yml up --build`.

This will create a Magento 2 application on port `8080` with this module installed.
Access the Magento admin panel at `http://localhost:8080/admin` (default credentials: `admin` / `Admin123!`).

## Quality

Run the quality checks:

```bash
composer quality
```

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Vincent Boon](https://github.com/VincentBean)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
