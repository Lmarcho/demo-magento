<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;

class TestConnection extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Lmarcho_RagSync::system/config/test_connection.phtml';

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for test connection button
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('ragsync/sync/testconnection');
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'ragsync_test_connection',
            'label' => __('Test Connection'),
            'class' => 'primary'
        ]);

        return $button->toHtml();
    }
}
