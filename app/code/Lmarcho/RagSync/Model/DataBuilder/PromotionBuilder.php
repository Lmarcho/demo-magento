<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\SalesRule\Api\Data\RuleInterface as CartRuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface as CartRuleRepositoryInterface;
use Magento\SalesRule\Model\Rule as CartRuleModel;
use Magento\CatalogRule\Api\Data\RuleInterface as CatalogRuleInterface;
use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\CatalogRule\Model\Rule as CatalogRuleModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Lmarcho\RagSync\Model\Config;

class PromotionBuilder
{
    public const TYPE_CART_RULE = 'cart_rule';
    public const TYPE_CATALOG_RULE = 'catalog_rule';

    /**
     * @var CartRuleRepositoryInterface
     */
    private CartRuleRepositoryInterface $cartRuleRepository;

    /**
     * @var CatalogRuleRepositoryInterface
     */
    private CatalogRuleRepositoryInterface $catalogRuleRepository;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param CartRuleRepositoryInterface $cartRuleRepository
     * @param CatalogRuleRepositoryInterface $catalogRuleRepository
     * @param DateTime $dateTime
     * @param Config $config
     */
    public function __construct(
        CartRuleRepositoryInterface $cartRuleRepository,
        CatalogRuleRepositoryInterface $catalogRuleRepository,
        DateTime $dateTime,
        Config $config
    ) {
        $this->cartRuleRepository = $cartRuleRepository;
        $this->catalogRuleRepository = $catalogRuleRepository;
        $this->dateTime = $dateTime;
        $this->config = $config;
    }

    /**
     * Build cart rule data for sync
     *
     * @param int $ruleId
     * @return array|null
     */
    public function buildCartRule(int $ruleId): ?array
    {
        try {
            $rule = $this->cartRuleRepository->getById($ruleId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromCartRule($rule);
    }

    /**
     * Build catalog rule data for sync
     *
     * @param int $ruleId
     * @return array|null
     */
    public function buildCatalogRule(int $ruleId): ?array
    {
        try {
            $rule = $this->catalogRuleRepository->get($ruleId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromCatalogRule($rule);
    }

    /**
     * Build data from cart rule object
     *
     * @param CartRuleInterface $rule
     * @return array
     */
    public function buildFromCartRule(CartRuleInterface $rule): array
    {
        $data = [
            'id' => (int)$rule->getRuleId(),
            'rule_type' => self::TYPE_CART_RULE,
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'is_active' => (bool)$rule->getIsActive(),
            'from_date' => $rule->getFromDate(),
            'to_date' => $rule->getToDate(),
            'coupon_type' => $this->getCouponTypeLabel((int)$rule->getCouponType()),
            'coupon_code' => $this->getPublicCouponCode($rule),
            'uses_per_coupon' => (int)$rule->getUsesPerCoupon(),
            'uses_per_customer' => (int)$rule->getUsesPerCustomer(),
            'discount_amount' => (float)$rule->getDiscountAmount(),
            'discount_type' => $this->getDiscountActionLabel($rule->getSimpleAction()),
            'discount_qty' => (float)$rule->getDiscountQty(),
            'discount_step' => (int)$rule->getDiscountStep(),
            'simple_free_shipping' => (bool)$rule->getSimpleFreeShipping(),
            'apply_to_shipping' => (bool)$rule->getApplyToShipping(),
            'stop_rules_processing' => (bool)$rule->getStopRulesProcessing(),
            'sort_order' => (int)$rule->getSortOrder(),
            'website_ids' => $rule->getWebsiteIds(),
            'customer_group_ids' => $rule->getCustomerGroupIds(),
            'conditions_summary' => $this->getConditionsSummary($rule),
            'is_expired' => $this->isExpired($rule->getToDate()),
            'document_type' => 'promotion',
            'document_subtype' => 'cart_rule',
        ];

        return $data;
    }

    /**
     * Build data from catalog rule object
     *
     * @param CatalogRuleInterface $rule
     * @return array
     */
    public function buildFromCatalogRule(CatalogRuleInterface $rule): array
    {
        $data = [
            'id' => (int)$rule->getRuleId(),
            'rule_type' => self::TYPE_CATALOG_RULE,
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'is_active' => (bool)$rule->getIsActive(),
            'from_date' => $rule->getFromDate(),
            'to_date' => $rule->getToDate(),
            'discount_amount' => (float)$rule->getDiscountAmount(),
            'discount_type' => $this->getCatalogDiscountActionLabel($rule->getSimpleAction()),
            'stop_rules_processing' => (bool)$rule->getStopRulesProcessing(),
            'sort_order' => (int)$rule->getSortOrder(),
            'website_ids' => $rule->getWebsiteIds(),
            'customer_group_ids' => $rule->getCustomerGroupIds(),
            'is_expired' => $this->isExpired($rule->getToDate()),
            'document_type' => 'promotion',
            'document_subtype' => 'catalog_rule',
        ];

        return $data;
    }

    /**
     * Get coupon type label
     *
     * @param int $couponType
     * @return string
     */
    private function getCouponTypeLabel(int $couponType): string
    {
        $labels = [
            1 => 'No Coupon',
            2 => 'Specific Coupon',
            3 => 'Auto Generated',
        ];

        return $labels[$couponType] ?? 'Unknown';
    }

    /**
     * Get cart rule discount action label
     *
     * @param string|null $action
     * @return string
     */
    private function getDiscountActionLabel(?string $action): string
    {
        $labels = [
            'by_percent' => 'Percent of product price discount',
            'by_fixed' => 'Fixed amount discount',
            'cart_fixed' => 'Fixed amount discount for whole cart',
            'buy_x_get_y' => 'Buy X get Y free',
        ];

        return $labels[$action] ?? $action ?? 'Unknown';
    }

    /**
     * Get catalog rule discount action label
     *
     * @param string|null $action
     * @return string
     */
    private function getCatalogDiscountActionLabel(?string $action): string
    {
        $labels = [
            'by_percent' => 'Apply as percentage of original',
            'by_fixed' => 'Apply as fixed amount',
            'to_percent' => 'Adjust final price to this percentage',
            'to_fixed' => 'Adjust final price to discount value',
        ];

        return $labels[$action] ?? $action ?? 'Unknown';
    }

    /**
     * Get public coupon code (only if visible to customers)
     *
     * @param CartRuleInterface $rule
     * @return string|null
     */
    private function getPublicCouponCode(CartRuleInterface $rule): ?string
    {
        // Only return coupon code for specific coupon type
        if ((int)$rule->getCouponType() !== 2) {
            return null;
        }

        $couponCode = $rule->getCouponCode();

        // Don't expose auto-generated or complex coupon codes
        if (empty($couponCode) || strlen($couponCode) > 20) {
            return null;
        }

        return $couponCode;
    }

    /**
     * Get human-readable conditions summary
     *
     * @param CartRuleInterface $rule
     * @return string|null
     */
    private function getConditionsSummary(CartRuleInterface $rule): ?string
    {
        $conditions = [];

        // Check minimum purchase amount
        $conditionData = $rule->getCondition();
        if ($conditionData) {
            // This is simplified - in production you'd parse the conditions array
            // For now, just check for common conditions
        }

        // Uses per customer
        if ($rule->getUsesPerCustomer() > 0) {
            $conditions[] = sprintf('Limited to %d uses per customer', $rule->getUsesPerCustomer());
        }

        // Date restrictions
        if ($rule->getFromDate() && $rule->getToDate()) {
            $conditions[] = sprintf(
                'Valid from %s to %s',
                date('M j, Y', strtotime($rule->getFromDate())),
                date('M j, Y', strtotime($rule->getToDate()))
            );
        }

        return !empty($conditions) ? implode('. ', $conditions) : null;
    }

    /**
     * Check if promotion is expired
     *
     * @param string|null $toDate
     * @return bool
     */
    private function isExpired(?string $toDate): bool
    {
        if (empty($toDate)) {
            return false;
        }

        $now = strtotime($this->dateTime->gmtDate());
        $endDate = strtotime($toDate);

        return $endDate < $now;
    }

    /**
     * Check if cart rule should be synced based on config
     *
     * @param CartRuleInterface|CartRuleModel $rule
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCartRule(CartRuleInterface|CartRuleModel $rule, ?int $storeId = null): bool
    {
        if (!$this->config->isPromotionSyncEnabled($storeId)) {
            return false;
        }

        if (!$this->config->shouldSyncCartRules($storeId)) {
            return false;
        }

        // Check if inactive rules should be included
        if (!$this->config->includeInactivePromotions($storeId)) {
            if (!$rule->getIsActive()) {
                return false;
            }
        }

        // Check if expired rules should be included
        if (!$this->config->includeExpiredPromotions($storeId)) {
            if ($this->isExpired($rule->getToDate())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if catalog rule should be synced based on config
     *
     * @param CatalogRuleInterface|CatalogRuleModel $rule
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCatalogRule(CatalogRuleInterface|CatalogRuleModel $rule, ?int $storeId = null): bool
    {
        if (!$this->config->isPromotionSyncEnabled($storeId)) {
            return false;
        }

        if (!$this->config->shouldSyncCatalogRules($storeId)) {
            return false;
        }

        // Check if inactive rules should be included
        if (!$this->config->includeInactivePromotions($storeId)) {
            if (!$rule->getIsActive()) {
                return false;
            }
        }

        // Check if expired rules should be included
        if (!$this->config->includeExpiredPromotions($storeId)) {
            if ($this->isExpired($rule->getToDate())) {
                return false;
            }
        }

        return true;
    }
}
