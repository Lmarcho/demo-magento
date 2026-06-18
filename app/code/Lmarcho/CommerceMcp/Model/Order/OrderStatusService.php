<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Order;

use Lmarcho\CommerceMcp\Api\CustomerAssertionServiceInterface;
use Lmarcho\CommerceMcp\Api\OrderStatusServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderStatusService implements OrderStatusServiceInterface
{
    public function __construct(
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly CustomerAssertionServiceInterface $customerAssertionService,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    public function get(string $storeCode, string $orderNumber, string $customerAssertion): array
    {
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '' || strlen($orderNumber) > 32 || preg_match('/\A[a-zA-Z0-9_-]+\z/', $orderNumber) !== 1) {
            throw $this->invalidArguments('INVALID_ORDER_NUMBER');
        }
        if (trim($customerAssertion) === '') {
            throw $this->invalidArguments('CUSTOMER_ASSERTION_REQUIRED');
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $claims = $this->customerAssertionService->verify(
            $customerAssertion,
            $context->getStoreId(),
            $context->getWebsiteId()
        );
        $order = $this->loadOrder($orderNumber, $context->getStoreId());
        if (!$order instanceof Order
            || !$order->getId()
            || (int)$order->getCustomerId() !== $claims['customer_id']
            || (int)$order->getStoreId() !== $context->getStoreId()
        ) {
            throw $this->orderNotAccessible();
        }

        return ['order' => $this->serialize($order)];
    }

    public function verifyGuest(string $storeCode, string $orderNumber, string $contact): array
    {
        $orderNumber = trim($orderNumber);
        $contact = trim($contact);
        if ($orderNumber === '' || strlen($orderNumber) > 32 || preg_match('/\A[a-zA-Z0-9_-]+\z/', $orderNumber) !== 1) {
            throw $this->invalidArguments('INVALID_ORDER_NUMBER');
        }
        if (!$this->isValidGuestContact($contact)) {
            throw $this->invalidArguments('INVALID_GUEST_CONTACT');
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $order = $this->loadOrder($orderNumber, $context->getStoreId());
        if (!$order instanceof Order
            || !$order->getId()
            || (int)$order->getStoreId() !== $context->getStoreId()
            || !$this->guestContactMatches($order, $contact)
        ) {
            throw $this->orderNotAccessible();
        }

        return ['order' => $this->serializeGuest($order)];
    }

    private function loadOrder(string $orderNumber, int $storeId): ?Order
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('entity_id')
            ->setDescendingDirection()
            ->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderNumber)
            ->addFilter('store_id', $storeId)
            ->setPageSize(1)
            ->setCurrentPage(1)
            ->setSortOrders([$sortOrder])
            ->create();
        $orders = $this->orderRepository->getList($criteria)->getItems();
        $order = reset($orders);

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function serialize(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'sku' => (string)$item->getSku(),
                'name' => (string)$item->getName(),
                'quantity' => (float)$item->getQtyOrdered(),
            ];
        }

        return [
            'order_number' => (string)$order->getIncrementId(),
            'status' => (string)$order->getStatus(),
            'status_label' => (string)$order->getStatusLabel(),
            'placed_at' => $this->dateToUtc($order->getCreatedAt()),
            'currency' => (string)$order->getOrderCurrencyCode(),
            'grand_total' => round((float)$order->getGrandTotal(), 2),
            'items' => $items,
            'shipments' => $this->shipments($order),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeGuest(Order $order): array
    {
        return [
            'order_number' => (string)$order->getIncrementId(),
            'status' => (string)$order->getStatus(),
            'status_label' => (string)$order->getStatusLabel(),
            'placed_at' => $this->dateToUtc($order->getCreatedAt()),
            'currency' => (string)$order->getOrderCurrencyCode(),
            'grand_total' => round((float)$order->getGrandTotal(), 2),
            'items_count' => count($order->getAllVisibleItems()),
            'shipping_description' => $this->nullableString($order->getShippingDescription()),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function shipments(Order $order): array
    {
        $shipments = [];
        $collection = $order->getShipmentsCollection();
        if ($collection === false) {
            return [];
        }
        foreach ($collection as $shipment) {
            $tracks = [];
            foreach ($shipment->getAllTracks() as $track) {
                $trackingNumber = (string)$track->getTrackNumber();
                $tracks[] = [
                    'carrier' => (string)($track->getTitle() ?: $track->getCarrierCode()),
                    'tracking_number' => $trackingNumber,
                    'tracking_url' => $this->trackingUrl((string)$track->getCarrierCode(), $trackingNumber),
                ];
            }
            $shipments[] = [
                'shipment_number' => (string)$shipment->getIncrementId(),
                'tracks' => $tracks,
            ];
        }

        return $shipments;
    }

    private function trackingUrl(string $carrierCode, string $trackingNumber): ?string
    {
        $template = $this->config->getTrackingUrlTemplates()[strtolower($carrierCode)] ?? null;
        if ($template === null || $trackingNumber === '') {
            return null;
        }

        return str_replace('{tracking_number}', rawurlencode($trackingNumber), $template);
    }

    private function dateToUtc(mixed $date): ?string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return null;
        }

        return gmdate('c', strtotime($date . ' UTC') ?: 0);
    }

    private function isValidGuestContact(string $contact): bool
    {
        if ($contact === '' || strlen($contact) > 190) {
            return false;
        }

        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $contact) ?: '';

        return strlen($digits) >= 6 && preg_match('/\A[0-9+()\-\s.]+\z/', $contact) === 1;
    }

    private function guestContactMatches(Order $order, string $contact): bool
    {
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $submitted = strtolower($contact);
            $emails = array_filter([
                strtolower((string)$order->getCustomerEmail()),
                strtolower((string)$order->getBillingAddress()?->getEmail()),
            ]);

            return in_array($submitted, $emails, true);
        }

        $submitted = preg_replace('/\D+/', '', $contact) ?: '';
        if (strlen($submitted) < 6) {
            return false;
        }

        $phones = array_filter([
            preg_replace('/\D+/', '', (string)$order->getBillingAddress()?->getTelephone()) ?: '',
            preg_replace('/\D+/', '', (string)$order->getShippingAddress()?->getTelephone()) ?: '',
        ]);

        return in_array($submitted, $phones, true);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid tool arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }

    private function orderNotAccessible(): JsonRpcException
    {
        return new JsonRpcException(
            'Order is not accessible',
            -32602,
            null,
            ['error_code' => 'ORDER_NOT_ACCESSIBLE']
        );
    }
}
