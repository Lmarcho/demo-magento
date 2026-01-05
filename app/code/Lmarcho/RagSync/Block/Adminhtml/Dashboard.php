<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\CircuitBreaker;
use Lmarcho\RagSync\Model\WebhookSender;

class Dashboard extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Lmarcho_RagSync::dashboard.phtml';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CircuitBreaker
     */
    private CircuitBreaker $circuitBreaker;

    /**
     * @var WebhookSender
     */
    private WebhookSender $webhookSender;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var PageCollectionFactory
     */
    private PageCollectionFactory $pageCollectionFactory;

    /**
     * @var BlockCollectionFactory
     */
    private BlockCollectionFactory $blockCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var CartRuleCollectionFactory
     */
    private CartRuleCollectionFactory $cartRuleCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param Config $config
     * @param QueueService $queueService
     * @param CircuitBreaker $circuitBreaker
     * @param WebhookSender $webhookSender
     * @param ProductCollectionFactory $productCollectionFactory
     * @param PageCollectionFactory $pageCollectionFactory
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CartRuleCollectionFactory $cartRuleCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        QueueService $queueService,
        CircuitBreaker $circuitBreaker,
        WebhookSender $webhookSender,
        ProductCollectionFactory $productCollectionFactory,
        PageCollectionFactory $pageCollectionFactory,
        BlockCollectionFactory $blockCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CartRuleCollectionFactory $cartRuleCollectionFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->queueService = $queueService;
        $this->circuitBreaker = $circuitBreaker;
        $this->webhookSender = $webhookSender;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->cartRuleCollectionFactory = $cartRuleCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Check if connection is configured
     *
     * @return bool
     */
    public function isConnectionConfigured(): bool
    {
        return $this->config->isConnectionConfigured();
    }

    /**
     * Get connection status
     *
     * @return array
     */
    public function getConnectionStatus(): array
    {
        if (!$this->isConnectionConfigured()) {
            return [
                'connected' => false,
                'message' => __('Connection not configured'),
                'latency' => null,
            ];
        }

        $response = $this->webhookSender->testConnection();

        return [
            'connected' => $response->isSuccess(),
            'message' => $response->isSuccess() ? __('Connected') : $response->getErrorMessage(),
            'latency' => $response->getDurationMs(),
            'status_code' => $response->getStatusCode(),
        ];
    }

    /**
     * Get webhook URL
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->config->getWebhookUrl();
    }

    /**
     * Get tenant ID
     *
     * @return string
     */
    public function getTenantId(): string
    {
        return $this->config->getTenantId();
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getQueueStats(): array
    {
        return $this->queueService->getStatistics();
    }

    /**
     * Get oldest pending item age
     *
     * @return int|null
     */
    public function getOldestPendingAge(): ?int
    {
        return $this->queueService->getOldestPendingAgeMinutes();
    }

    /**
     * Get circuit breaker status
     *
     * @return array
     */
    public function getCircuitBreakerStatus(): array
    {
        return $this->circuitBreaker->getStatus();
    }

    /**
     * Get entity sync status
     *
     * @return array
     */
    public function getEntitySyncStatus(): array
    {
        return [
            'products' => [
                'enabled' => $this->config->isProductSyncEnabled(),
                'count' => $this->getProductCount(),
                'label' => __('Products'),
            ],
            'cms_pages' => [
                'enabled' => $this->config->isCmsPageSyncEnabled(),
                'count' => $this->getCmsPageCount(),
                'label' => __('CMS Pages'),
            ],
            'cms_blocks' => [
                'enabled' => $this->config->isCmsBlockSyncEnabled(),
                'count' => $this->getCmsBlockCount(),
                'label' => __('CMS Blocks'),
            ],
            'categories' => [
                'enabled' => $this->config->isCategorySyncEnabled(),
                'count' => $this->getCategoryCount(),
                'label' => __('Categories'),
            ],
            'promotions' => [
                'enabled' => $this->config->isPromotionSyncEnabled(),
                'count' => $this->getPromotionCount(),
                'label' => __('Promotions'),
            ],
            'store_config' => [
                'enabled' => $this->config->isEnabled(),
                'count' => $this->getStoreCount(),
                'label' => __('Store Config'),
            ],
        ];
    }

    /**
     * Get product count
     *
     * @return int
     */
    private function getProductCount(): int
    {
        $collection = $this->productCollectionFactory->create();
        return $collection->getSize();
    }

    /**
     * Get CMS page count
     *
     * @return int
     */
    private function getCmsPageCount(): int
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        return $collection->getSize();
    }

    /**
     * Get CMS block count
     *
     * @return int
     */
    private function getCmsBlockCount(): int
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        return $collection->getSize();
    }

    /**
     * Get category count
     *
     * @return int
     */
    private function getCategoryCount(): int
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('level', ['gteq' => $this->config->getCategoryMinLevel()]);
        return $collection->getSize();
    }

    /**
     * Get promotion count
     *
     * @return int
     */
    private function getPromotionCount(): int
    {
        $collection = $this->cartRuleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        return $collection->getSize();
    }

    /**
     * Get store count
     *
     * @return int
     */
    private function getStoreCount(): int
    {
        return count($this->storeManager->getStores());
    }

    /**
     * Get sync URL for entity type
     *
     * @param string $entityType
     * @return string
     */
    public function getSyncUrl(string $entityType): string
    {
        return $this->getUrl('ragsync/sync/entity', ['type' => $entityType]);
    }

    /**
     * Get process queue URL
     *
     * @return string
     */
    public function getProcessQueueUrl(): string
    {
        return $this->getUrl('ragsync/sync/processQueue');
    }

    /**
     * Get retry failed URL
     *
     * @return string
     */
    public function getRetryFailedUrl(): string
    {
        return $this->getUrl('ragsync/sync/retryFailed');
    }

    /**
     * Get clear sent URL
     *
     * @return string
     */
    public function getClearSentUrl(): string
    {
        return $this->getUrl('ragsync/sync/clearSent');
    }

    /**
     * Get test connection URL
     *
     * @return string
     */
    public function getTestConnectionUrl(): string
    {
        return $this->getUrl('ragsync/sync/testConnection');
    }

    /**
     * Get queue grid URL
     *
     * @return string
     */
    public function getQueueGridUrl(): string
    {
        return $this->getUrl('ragsync/queue/index');
    }

    /**
     * Get configuration URL
     *
     * @return string
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'rag_sync']);
    }

    /**
     * Get environment
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return ucfirst($this->config->getEnvironment());
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return $this->config->isDebugEnabled();
    }
}
