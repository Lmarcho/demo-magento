<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Lmarcho\RagSync\Model\Config;

class CmsBlockBuilder
{
    /**
     * @var BlockRepositoryInterface
     */
    private BlockRepositoryInterface $blockRepository;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param BlockRepositoryInterface $blockRepository
     * @param Config $config
     */
    public function __construct(
        BlockRepositoryInterface $blockRepository,
        Config $config
    ) {
        $this->blockRepository = $blockRepository;
        $this->config = $config;
    }

    /**
     * Build CMS block data for sync
     *
     * @param int $blockId
     * @return array|null
     */
    public function build(int $blockId): ?array
    {
        try {
            $block = $this->blockRepository->getById($blockId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromBlock($block);
    }

    /**
     * Build CMS block data from block object
     *
     * @param BlockInterface $block
     * @return array
     */
    public function buildFromBlock(BlockInterface $block): array
    {
        $data = [
            'id' => (int)$block->getId(),
            'identifier' => $block->getIdentifier(),
            'title' => $block->getTitle(),
            'content' => $this->cleanHtml($block->getContent()),
            'is_active' => (bool)$block->isActive(),
            'store_ids' => $block->getStoreId(),
            'document_type' => 'general',
            'document_subtype' => 'cms_block',
            'creation_time' => $block->getCreationTime(),
            'update_time' => $block->getUpdateTime(),
        ];

        return $data;
    }

    /**
     * Clean HTML from content
     *
     * @param string|null $html
     * @return string|null
     */
    private function cleanHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        // Remove widget directives
        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);

        // Remove HTML tags but keep text
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text ?: null;
    }

    /**
     * Check if CMS block should be synced based on config
     *
     * @param BlockInterface $block
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSync(BlockInterface $block, ?int $storeId = null): bool
    {
        $identifier = $block->getIdentifier();
        return $this->config->shouldSyncCmsBlock($identifier, $storeId);
    }
}
