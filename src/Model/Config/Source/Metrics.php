<?php

namespace Vigilant\MagentoHealthchecks\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Metrics implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'memory_usage', 'label' => __('Memory usage')],
            ['value' => 'cpu_load', 'label' => __('CPU load')],
            ['value' => 'disk_usage', 'label' => __('Disk usage')],
            ['value' => 'indexers_invalid', 'label' => __('Indexers in invalid state')],
            ['value' => 'indexers_working', 'label' => __('Indexers currently running')],
            ['value' => 'indexers_valid', 'label' => __('Indexers valid count')],
            ['value' => 'indexer_working_minutes', 'label' => __('Indexer working minutes')],
        ];
    }
}
