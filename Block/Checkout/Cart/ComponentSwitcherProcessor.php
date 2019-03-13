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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Block\Checkout\Cart;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class ComponentSwitcherProcessor
 * Enable / disable components on the shopping cart page depending on the configuration.
 *
 * @package Bolt\Boltpay\Block\Checkout\Cart
 */
class ComponentSwitcherProcessor implements LayoutProcessorInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param ConfigHelper      $configHelper
     * @codeCoverageIgnore
     */
    public function __construct(
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
    }

    public function process($jsLayout) {
        // Store Credit
        $jsLayout['components']['block-totals']['children']['storeCredit']['componentDisabled'] =
            ! $this->configHelper->useStoreCreditConfig();

        return $jsLayout;
    }
}
