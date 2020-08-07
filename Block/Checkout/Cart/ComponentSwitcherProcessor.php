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
namespace Bolt\Boltpay\Block\Checkout\Cart;

use Bolt\Boltpay\Block\Checkout\LayoutProcessor;

/**
 * Class ComponentSwitcherProcessor
 * Enable / disable components in the shopping cart page layout depending on the configuration.
 */
class ComponentSwitcherProcessor extends LayoutProcessor
{
    /**
     * Process the layout
     *
     * @param  array $jsLayout
     * @return array
     */
    public function process($jsLayout)
    {
        // Store Credit
        if (! $this->configHelper->useStoreCreditConfig()) {
            unset($jsLayout['components']['block-totals']['children']['storeCredit']);
        }
        // Reward Points
        if (! $this->configHelper->useRewardPointsConfig()) {
            unset($jsLayout['components']['block-totals']['children']['rewardPoints']);
        }
        // Amasty Store Credit
        if (! $this->configHelper->useAmastyStoreCreditConfig()) {
            unset($jsLayout['components']['block-totals']['children']['amstorecredit_total']);
            unset($jsLayout['components']['block-totals']['children']['amstorecredit_form']);
        }
        return $jsLayout;
    }
}
