<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Template;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\IntegrationManagement;

class IntegrationConnection extends Field
{
    const IntegrationConnection_BLOCK_TEMPLATE = 'Bolt_Boltpay::system/config/integrationconnection.phtml';
    const IntegrationConnection_BLOCK_NAME     = "Bolt_Boltpay.adminhtml.config.integrationconnection.template";
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @var Bolt\Boltpay\Helper\IntegrationManagement
     */
    protected $integrationManagement;
    
    /**
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param IntegrationManagement $integrationManagement
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        IntegrationManagement $integrationManagement,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->integrationManagement = $integrationManagement;
    }
    
    /**
     * Retrieve element HTML markup
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $integrationStatus = $this->integrationManagement->getIntegrationStatus($storeId);
        $isBoltSandboxMode = $this->configHelper->isSandboxModeSet($storeId);
        $boltCurrentMode = $isBoltSandboxMode ? IntegrationManagement::BOLT_INTEGRATION_MODE_SANDBOX : IntegrationManagement::BOLT_INTEGRATION_MODE_PRODUCTION;
        $boltOppositeMode = $isBoltSandboxMode ? IntegrationManagement::BOLT_INTEGRATION_MODE_PRODUCTION : IntegrationManagement::BOLT_INTEGRATION_MODE_SANDBOX;
        $block = $this->getLayout()->createBlock(
                Template::class,
                self::IntegrationConnection_BLOCK_NAME,
                [
                    'data' => [
                        'input_id' => $element->getId(),
                        'process_integration_token_ajax_url' => $this->getProcessIntegrationTokenAjaxUrl(),
                        'integration_status' => $integrationStatus,
                        'store_id' => $storeId,
                        'bolt_current_mode' => $boltCurrentMode,
                        'bolt_opposite_mode' => $boltOppositeMode,
                    ]
                ]
            );
        $block->setTemplate(self::IntegrationConnection_BLOCK_TEMPLATE);
      
        return ''
            . parent::_getElementHtml($element)
            . $this->getGenerateTokenButtonHtml($integrationStatus)
            . $block->toHtml()
        ;
    }
    
    /**
     * Return ajax url for processing access token of Bolt associated Magento integration.
     *
     * @return string
     */
    public function getProcessIntegrationTokenAjaxUrl()
    {
        return $this->getUrl('boltpay/system/processIntegrationToken');
    }
    
    /**
     * Create button html to process access token of Bolt associated Magento integration.
     *
     * @return string
     */
    public function getGenerateTokenButtonHtml($integrationStatus)
    {
        switch ($integrationStatus) {
            case '1':
                $label = __('Re-send API keys to Bolt');
                break;
            case '2':
                $label = __('Delete API keys');
                break;
            default:
                $label = __('Authenticate with Bolt');
                break;
        }
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'bolt_integration_token_button',
                'label' => $label,
            ]
        );

        return $button->toHtml();
    }
}
