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

namespace Bolt\Boltpay\ViewModel;

/**
 * MinicartAddons view model
 *
 * @package Bolt\Boltpay\ViewModel
 */
class MinicartAddons implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var \Bolt\Boltpay\Helper\Config instance of the Bolt configuration helper
     */
    public $configHelper;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface JSON serializer instance
     */
    private $serializer;

    /**
     * @var \Magento\Framework\App\Http\Context request context data
     */
    private $httpContext;

    /**
     * @var array layout updates that need to be applied to minicart Ui component layout
     */
    private $_layout;

    /**
     * MinicartAddons constructor.
     *
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer JSON serializer instance
     * @param \Magento\Framework\App\Http\Context              $httpContext request context data
     * @param \Bolt\Boltpay\Helper\Config                      $config instance of the Bolt configuration helper
     */
    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\App\Http\Context $httpContext,
        \Bolt\Boltpay\Helper\Config $config
    ) {
        $this->configHelper = $config;
        $this->serializer = $serializer;
        $this->httpContext = $httpContext;
    }

    /**
     * Gets layout updates that need to be applied to minicart Ui component layout
     *
     * @return array minicart layout updates
     */
    protected function getLayout()
    {
        if ($this->_layout === null) {
            $this->_layout = [];
            if ($this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH)
                && $this->configHelper->displayRewardPointsInMinicartConfig()) {
                $this->_layout[] = [
                    'parent'    => 'minicart_content.extra_info',
                    'name'      => 'minicart_content.extra_info.rewards',
                    'component' => 'Magento_Reward/js/view/payment/reward',
                    'config'    => [],
                ];
                $this->_layout[] = [
                    'parent'    => 'minicart_content.extra_info',
                    'name'      => 'minicart_content.extra_info.rewards_total',
                    'component' => 'Magento_Reward/js/view/cart/reward',
                    'config'    => [
                        'template' => 'Magento_Reward/cart/reward',
                        'title'    => 'Reward Points',
                    ],
                ];
            }
        }
        return $this->_layout;
    }

    /**
     * Gets layout updates that need to be applied to minicart Ui component layout in JSON
     *
     * @return string JSON minicart layout updates
     */
    public function getLayoutJSON()
    {
        return $this->serializer->serialize($this->getLayout());
    }

    /**
     * Return true if Bolt on minicart is enabled and at least one minicart addon is enabled
     * (currently only reward points)
     *
     * @return bool whether or not to apply addons
     */
    public function shouldShow()
    {
        return $this->configHelper->getMinicartSupport() && !empty($this->getLayout());
    }

}