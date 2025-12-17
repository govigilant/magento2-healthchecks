<?php

namespace Vigilant\MagentoHealthchecks\Checks\Metrics;

use Throwable;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Data\MetricData;

class IndexerMetric extends Metric
{
    protected string $type = 'indexers';

    public function __construct(
        protected readonly IndexerCollectionProvider $collectionProvider,
        protected readonly string $status
    ) {
        $this->type = sprintf('indexers_%s', $status);
    }

    public function measure(): MetricData
    {
        $count = 0;

        try {
            foreach ($this->collectionProvider->getIndexers() as $indexer) {
                $state = $indexer->getState();

                if ($state && $state->getStatus() === $this->status) {
                    $count++;
                }
            }
        } catch (Throwable) {
            $count = 0;
        }

        return MetricData::make([
            'type' => $this->type(),
            'key' => $this->status,
            'value' => $count,
            'unit' => 'indexers',
        ]);
    }

    public function available(): bool
    {
        return class_exists(\Magento\Indexer\Model\Indexer\CollectionFactory::class);
    }
}
