<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Lmarcho\RagSync\Model\Queue;

class Status extends Column
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[$this->getData('name')])) {
                    $status = $item[$this->getData('name')];
                    $item[$this->getData('name')] = $this->getStatusHtml($status);
                }
            }
        }

        return $dataSource;
    }

    /**
     * Get status HTML with badge styling
     *
     * @param string $status
     * @return string
     */
    private function getStatusHtml(string $status): string
    {
        $statusConfig = [
            Queue::STATUS_PENDING => [
                'label' => __('Pending'),
                'class' => 'grid-severity-minor',
            ],
            Queue::STATUS_PROCESSING => [
                'label' => __('Processing'),
                'class' => 'grid-severity-minor',
            ],
            Queue::STATUS_SENT => [
                'label' => __('Sent'),
                'class' => 'grid-severity-notice',
            ],
            Queue::STATUS_FAILED => [
                'label' => __('Failed'),
                'class' => 'grid-severity-critical',
            ],
            Queue::STATUS_DEAD => [
                'label' => __('Dead'),
                'class' => 'grid-severity-critical',
            ],
        ];

        $config = $statusConfig[$status] ?? [
            'label' => $status,
            'class' => 'grid-severity-minor',
        ];

        return sprintf(
            '<span class="%s"><span>%s</span></span>',
            $config['class'],
            $config['label']
        );
    }
}
