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
use Bolt\Boltpay\Helper\IntegrationManagement;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

class IntegrationConnection extends Field
{
    const IntegrationConnection_BLOCK_TEMPLATE = 'Bolt_Boltpay::system/config/integrationconnection.phtml';
    const IntegrationConnection_BLOCK_NAME     = "Bolt_Boltpay.adminhtml.config.integrationconnection.template";
    
    /**
     * @var Bolt\Boltpay\Helper\IntegrationManagement
     */
    protected $integrationManagement;
    
    /**
     * @var Bolt\Boltpay\Helper\Config
     */
    protected $configHelper;
    
    /**
     * @param Context $context
     * @param IntegrationManagement $integrationManagement
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        IntegrationManagement $integrationManagement,
        ConfigHelper $configHelper,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->integrationManagement = $integrationManagement;
        $this->configHelper = $configHelper;
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
        // Get access token of Bolt integration if exists
        $integrationToken = $this->integrationManagement->getMagentoIntegraionToken();
        $savedToken = $element->getValue();
        $tokenLinked = $this->configHelper->getLinkIntegrationFlag();
        // If the access token saved in the configuration is different from the one from Bolt integration,
        // it means either the Bolt integration is inactive or the access is reauthorized.
        if ($savedToken !== $integrationToken) {
            $savedToken = '';
        }
        $block = $this->getLayout()->createBlock(
                Template::class,
                self::IntegrationConnection_BLOCK_NAME,
                [
                    'data' => [
                        'input_id' => $element->getId(),
                        'generate_integration_token_ajax_url' => $this->getGenerateIntegrationTokenAjaxUrl(),
                        'link_integration_token_ajax_url' => $this->getLinkIntegrationTokenAjaxUrl(),
                        'key_value' => $savedToken,
                        'token_linked' => $tokenLinked,
                        'store_id' => $this->_storeManager->getStore()->getId(),
                    ]
                ]
            );
        $block->setTemplate(self::IntegrationConnection_BLOCK_TEMPLATE);
      
        return ''
            . parent::_getElementHtml($element)
            . $this->getGenerateTokenButtonHtml($savedToken)
            . $this->getLinkApiTokenButtonHtml($savedToken, $tokenLinked)
            . $block->toHtml()
        ;
    }
    
    /**
     * Return ajax url for generating access token of Magento integration.
     *
     * @return string
     */
    public function getGenerateIntegrationTokenAjaxUrl()
    {
        return $this->getUrl('boltpay/system/generateIntegrationToken');
    }
    
    /**
     * Return ajax url for linking access token of Magento integration to Bolt merchant account.
     *
     * @return string
     */
    public function getLinkIntegrationTokenAjaxUrl()
    {
        return $this->getUrl('boltpay/system/linkIntegrationToken');
    }
    
    /**
     * Create button html to generate access token of Magento integration.
     *
     * @return string
     */
    public function getGenerateTokenButtonHtml($value)
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'bolt_integration_token_button',
                'label' => __('Generate Integration Token'),
                'class' => !empty($value) ? 'hidden' : '',
            ]
        );

        return $button->toHtml();
    }
    
    /**
     * Create button html to link access token of Magento integration to Bolt merchant account.
     *
     * @return string
     */
    public function getLinkApiTokenButtonHtml($value, $tokenLinked)
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'bolt_link_api_token_button',
                'label' => __('Link API Token'),
                'class' => empty($value) || $tokenLinked ? 'hidden' : '',
            ]
        );

        return $button->toHtml();
    }
}
