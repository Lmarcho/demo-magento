<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Lmarcho\RagSync\Model\Config;

class CmsPageBuilder
{
    /**
     * Document type mapping based on page identifier patterns
     */
    private const DOCUMENT_TYPE_PATTERNS = [
        'policy' => [
            'patterns' => ['privacy', 'data-protection', 'terms', 'conditions', 'return', 'refund', 'warranty', 'guarantee'],
            'subtypes' => [
                'privacy_policy' => ['privacy', 'data-protection'],
                'terms' => ['terms', 'conditions'],
                'return_policy' => ['return', 'refund'],
                'warranty' => ['warranty', 'guarantee'],
            ],
        ],
        'shipping' => [
            'patterns' => ['shipping', 'delivery'],
            'subtypes' => [
                'shipping_policy' => ['shipping', 'delivery'],
            ],
        ],
        'faq' => [
            'patterns' => ['faq', 'frequently-asked', 'help', 'questions'],
            'subtypes' => [],
        ],
        'support' => [
            'patterns' => ['contact', 'reach-us', 'support', 'customer-service'],
            'subtypes' => [
                'contact' => ['contact', 'reach-us'],
            ],
        ],
        'guide' => [
            'patterns' => ['size-guide', 'fit-guide', 'how-to', 'guide', 'tutorial'],
            'subtypes' => [
                'size_guide' => ['size-guide', 'fit-guide'],
            ],
        ],
        'general' => [
            'patterns' => ['about', 'our-story', 'company', 'history'],
            'subtypes' => [
                'about' => ['about', 'our-story', 'company'],
            ],
        ],
    ];

    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param Config $config
     */
    public function __construct(
        PageRepositoryInterface $pageRepository,
        Config $config
    ) {
        $this->pageRepository = $pageRepository;
        $this->config = $config;
    }

    /**
     * Build CMS page data for sync
     *
     * @param int $pageId
     * @return array|null
     */
    public function build(int $pageId): ?array
    {
        try {
            $page = $this->pageRepository->getById($pageId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromPage($page);
    }

    /**
     * Build CMS page data from page object
     *
     * @param PageInterface $page
     * @return array
     */
    public function buildFromPage(PageInterface $page): array
    {
        $identifier = $page->getIdentifier();
        $documentType = $this->determineDocumentType($identifier);

        $data = [
            'id' => (int)$page->getId(),
            'identifier' => $identifier,
            'title' => $page->getTitle(),
            'content' => $this->cleanHtml($page->getContent()),
            'content_heading' => $page->getContentHeading(),
            'meta_title' => $page->getMetaTitle(),
            'meta_description' => $page->getMetaDescription(),
            'meta_keywords' => $page->getMetaKeywords(),
            'is_active' => (bool)$page->isActive(),
            'store_ids' => $page->getStoreId(),
            'document_type' => $documentType['type'],
            'document_subtype' => $documentType['subtype'],
            'creation_time' => $page->getCreationTime(),
            'update_time' => $page->getUpdateTime(),
        ];

        return $data;
    }

    /**
     * Determine document type based on page identifier
     *
     * @param string $identifier
     * @return array ['type' => string, 'subtype' => string|null]
     */
    public function determineDocumentType(string $identifier): array
    {
        $identifier = strtolower($identifier);

        foreach (self::DOCUMENT_TYPE_PATTERNS as $type => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($identifier, $pattern)) {
                    // Check for subtype
                    $subtype = null;
                    foreach ($config['subtypes'] as $subtypeName => $subtypePatterns) {
                        foreach ($subtypePatterns as $subtypePattern) {
                            if (str_contains($identifier, $subtypePattern)) {
                                $subtype = $subtypeName;
                                break 2;
                            }
                        }
                    }

                    return [
                        'type' => $type,
                        'subtype' => $subtype,
                    ];
                }
            }
        }

        // Default to general
        return [
            'type' => 'general',
            'subtype' => null,
        ];
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
     * Check if CMS page should be synced based on config
     *
     * @param PageInterface $page
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSync(PageInterface $page, ?int $storeId = null): bool
    {
        $identifier = $page->getIdentifier();
        return $this->config->shouldSyncCmsPage($identifier, $storeId);
    }

    /**
     * Get all document type patterns (for reference/debugging)
     *
     * @return array
     */
    public function getDocumentTypePatterns(): array
    {
        return self::DOCUMENT_TYPE_PATTERNS;
    }
}
