<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Lmarcho\RagSync\Model\Queue;

class StatusOptions implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Queue::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => Queue::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => Queue::STATUS_SENT, 'label' => __('Sent')],
            ['value' => Queue::STATUS_FAILED, 'label' => __('Failed')],
            ['value' => Queue::STATUS_DEAD, 'label' => __('Dead')],
        ];
    }
}
