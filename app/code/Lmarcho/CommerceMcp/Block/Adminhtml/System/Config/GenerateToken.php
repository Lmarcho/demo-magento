<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Backend\Block\Widget\ButtonFactory;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateToken extends Field
{
    public function __construct(
        Context $context,
        private readonly ButtonFactory $buttonFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $element = clone $element;
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $button = $this->buttonFactory->create([
            'data' => [
                'id' => $element->getHtmlId(),
                'label' => __('Generate Access Token'),
                'class' => 'secondary',
            ],
        ]);

        $config = [
            'url' => $this->getUrl('commerce_mcp/client/generate'),
            'defaultName' => (string)__('Admin Generated Client'),
            'resultId' => $element->getHtmlId() . '_result',
        ];

        return $this->renderButton($button)
            . '<div id="' . $this->escapeHtmlAttr($config['resultId']) . '" class="commerce-mcp-token-result"></div>'
            . '<script type="text/x-magento-init">'
            . $this->jsonEncode([
                '#' . $element->getHtmlId() => [
                    'Lmarcho_CommerceMcp/js/generate-token' => $config,
                ],
            ])
            . '</script>';
    }

    private function renderButton(Button $button): string
    {
        return $button->toHtml();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonEncode(array $data): string
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );
    }
}
