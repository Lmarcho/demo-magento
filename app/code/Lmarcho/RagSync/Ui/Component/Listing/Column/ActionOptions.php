<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class ActionOptions implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'upsert', 'label' => __('Upsert')],
            ['value' => 'delete', 'label' => __('Delete')],
        ];
    }
}
