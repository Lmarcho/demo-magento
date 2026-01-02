<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';
    public const DEVELOPMENT = 'development';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PRODUCTION, 'label' => __('Production')],
            ['value' => self::STAGING, 'label' => __('Staging')],
            ['value' => self::DEVELOPMENT, 'label' => __('Development')],
        ];
    }
}
