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

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class LayoutProcessor
 * Parent layout processor class to be extended in ComponentSwitcherProcessor classes
 * and added as an argument to layoutProcessors arguments array.
 *
 * @package Bolt\Boltpay\Block\Checkout
 */
abstract class LayoutProcessor implements LayoutProcessorInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param ConfigHelper      $configHelper
     */
    public function __construct(
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
    }

    /**
     * Process the layout
     *
     * @param array $jsLayout
     * @return array
     */
    abstract public function process($jsLayout);
}
