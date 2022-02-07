<?php
declare(strict_types=1);

namespace Bolt\Boltpay\Block\Adminhtml\System\Config\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Bolt\Boltpay\Helper\Config;

class Version extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        Context $context,
        Config $config,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->configHelper = $config;
        parent::__construct(
            $context,
            $data,
            $secureRenderer
        );
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $version = $this->configHelper->getModuleVersion();

        if (!$version) {
            $version = __('--');
        }

        $output = '<div style="background-color:#eee;padding:1em;border:1px solid #ddd;">';
        $output .= __('Bolt M2 Version') . ': ' . $version;
        $output .= "</div>";

        return '<div id="row_' . $element->getHtmlId() . '">' . $output . '</div>';
    }
}
