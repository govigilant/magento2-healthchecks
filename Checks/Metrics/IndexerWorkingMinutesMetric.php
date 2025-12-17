<?php

namespace Vigilant\MagentoHealthchecks\Checks\Metrics;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Framework\Indexer\StateInterface;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Data\MetricData;

class IndexerWorkingMinutesMetric extends Metric
{
    protected string $type = 'indexer_working_minutes';

    public function __construct(
        protected readonly IndexerCollectionProvider $collectionProvider
    ) {}

    public function measure(): MetricData
    {
        $longestMinutes = 0;
        $longestIndexer = null;

        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            foreach ($this->collectionProvider->getIndexers() as $indexer) {
                $state = $indexer->getState();

                if (! $state || $state->getStatus() !== StateInterface::STATUS_WORKING) {
                    continue;
                }

                $updatedAt = $state->getUpdated();
                if (! $updatedAt) {
                    continue;
                }

                try {
                    $updated = new DateTimeImmutable($updatedAt, new DateTimeZone('UTC'));
                } catch (Exception) {
                    continue;
                }

                $minutes = (int) floor(max(0, $now->getTimestamp() - $updated->getTimestamp()) / 60);

                if ($minutes >= $longestMinutes) {
                    $longestMinutes = $minutes;
                    $longestIndexer = $indexer->getId() ?: $indexer->getIndexerId();
                }
            }
        } catch (Throwable) {
            $longestMinutes = 0;
            $longestIndexer = null;
        }

        return MetricData::make([
            'type' => $this->type(),
            'key' => $longestIndexer ? (string) $longestIndexer : null,
            'value' => $longestMinutes,
            'unit' => 'minutes',
        ]);
    }

    public function available(): bool
    {
        return class_exists(\Magento\Indexer\Model\Indexer\CollectionFactory::class);
    }
}
