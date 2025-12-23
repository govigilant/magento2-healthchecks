<?php

namespace Vigilant\MagentoHealthchecks\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Checks implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'database', 'label' => __('Database connection')],
            ['value' => 'cache', 'label' => __('Cache backends')],
            ['value' => 'debug_mode', 'label' => __('Developer mode status')],
            ['value' => 'disk_space', 'label' => __('Disk space availability')],
            ['value' => 'redis', 'label' => __('Redis services')],
            ['value' => 'search_engine', 'label' => __('Search engine configuration')],
            ['value' => 'message_queue', 'label' => __('Message queue status')],
            ['value' => 'cron', 'label' => __('Cron heartbeat')],
        ];
    }
}
