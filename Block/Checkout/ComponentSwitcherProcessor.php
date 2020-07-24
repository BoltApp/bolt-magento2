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
namespace Bolt\Boltpay\Block\Checkout;

use Bolt\Boltpay\Block\Checkout\LayoutProcessor;

/**
 * Class ComponentSwitcherProcessor
 * Enable / disable components in the checkout page layout depending on the configuration.
 */
class ComponentSwitcherProcessor extends LayoutProcessor
{
    /**
     * Process the layout
     *
     * @param array $jsLayout
     * @return array
     */
    public function process($jsLayout)
    {
        // Payment only checkout based on Magento default one-page checkpout
        // (one-page checkout, Mageplaza one-step checkout etc.)
        $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']
        ['children']['renders']['children']['boltpay-payments']['config']['componentDisabled'] =
            ! $this->configHelper->isPaymentOnlyCheckoutEnabled();

        return $jsLayout;
    }
}
