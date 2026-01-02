<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SyncMode implements OptionSourceInterface
{
    public const WHITELIST = 'whitelist';
    public const BLACKLIST = 'blacklist';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::WHITELIST, 'label' => __('Whitelist (Only sync specified)')],
            ['value' => self::BLACKLIST, 'label' => __('Blacklist (Sync all except specified)')],
        ];
    }
}
