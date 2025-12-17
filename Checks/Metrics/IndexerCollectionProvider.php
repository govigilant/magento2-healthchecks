<?php

namespace Vigilant\MagentoHealthchecks\Checks\Metrics;

use Magento\Indexer\Model\Indexer\Collection;
use Magento\Indexer\Model\Indexer\CollectionFactory;

class IndexerCollectionProvider
{
    private ?Collection $collection = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    /**
     * @return array<int, \Magento\Indexer\Model\IndexerInterface>
     */
    public function getIndexers(): array
    {
        if ($this->collection === null) {
            $this->collection = $this->collectionFactory->create();
        }

        if (! $this->collection->isLoaded()) {
            $this->collection->load();
        }

        return $this->collection->getItems();
    }
}
