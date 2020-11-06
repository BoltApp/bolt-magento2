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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block;

use Magento\Framework\View\Element\Template;

/**
 * MinicartAddons Block
 *
 * @package \Bolt\Boltpay\Block\MinicartAddons
 */
class MinicartAddons extends Template
{
    /**
     * @var \Bolt\Boltpay\Helper\Config instance of the Bolt configuration helper
     */
    public $configHelper;

    /**
     * @var \Magento\Framework\App\Http\Context request context data
     */
    private $httpContext;

    /**
     * @var array layout updates that need to be applied to minicart Ui component layout
     */
    private $_miniCartLayout;

    /**
     * MinicartAddons constructor.
     * @param Template\Context $context
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Bolt\Boltpay\Helper\Config $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\App\Http\Context $httpContext,
        \Bolt\Boltpay\Helper\Config $config,
        array $data = [])
    {
        $this->configHelper = $config;
        $this->httpContext = $httpContext;
        parent::__construct($context, $data);
    }


    /**
     * Gets layout updates that need to be applied to minicart Ui component layout
     *
     * @return array minicart layout updates
     */
    protected function getMiniCartLayout()
    {
        if ($this->_miniCartLayout === null) {
            $this->_miniCartLayout = [];
            if ($this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH) && $this->displayRewardPointsInMinicartConfig())
            {
                $this->_miniCartLayout[] = [
                    'parent' => 'minicart_content.extra_info',
                    'name' => 'minicart_content.extra_info.rewards',
                    'component' => 'Magento_Reward/js/view/payment/reward',
                    'config' => [],
                ];
                $this->_miniCartLayout[] = [
                    'parent' => 'minicart_content.extra_info',
                    'name' => 'minicart_content.extra_info.rewards_total',
                    'component' => 'Magento_Reward/js/view/cart/reward',
                    'config' => [
                        'template' => 'Magento_Reward/cart/reward',
                        'title' => 'Reward Points',
                    ],
                ];
            }
        }
        return $this->_miniCartLayout;
    }

    /**
     * Gets layout updates that need to be applied to minicart Ui component layout in JSON
     *
     * @return false|string JSON minicart layout updates
     */
    public function getLayoutJSON()
    {
        return json_encode($this->getMiniCartLayout());
    }

    /**
     * Return true if Bolt on minicart is enabled and at least one minicart addon is enabled
     * (currently only reward points)
     *
     * @return bool whether or not to apply addons
     */
    public function shouldShow()
    {
        return $this->configHelper->getMinicartSupport() && !empty($this->getMiniCartLayout());
    }

    /**
     * @return bool
     */
    public function displayRewardPointsInMinicartConfig() {
        return $this->configHelper->displayRewardPointsInMinicartConfig();
    }
}