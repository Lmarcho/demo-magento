<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RuleTypes implements OptionSourceInterface
{
    public const CART_RULES = 'cart';
    public const CATALOG_RULES = 'catalog';
    public const BOTH = 'both';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BOTH, 'label' => __('Both Cart & Catalog Rules')],
            ['value' => self::CART_RULES, 'label' => __('Cart Price Rules Only')],
            ['value' => self::CATALOG_RULES, 'label' => __('Catalog Price Rules Only')],
        ];
    }
}
