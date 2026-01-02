<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class EntityTypeOptions implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'product', 'label' => __('Product')],
            ['value' => 'cms_page', 'label' => __('CMS Page')],
            ['value' => 'cms_block', 'label' => __('CMS Block')],
            ['value' => 'category', 'label' => __('Category')],
            ['value' => 'cart_rule', 'label' => __('Cart Rule')],
            ['value' => 'catalog_rule', 'label' => __('Catalog Rule')],
        ];
    }
}
