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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ViewModel;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Payment;
use Magento\Sales\Model\Order;

/**
 * Order Comment view model
 */
class OrderComment implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * OrderComment constructor.
     *
     * @param Config  $config          Bolt configuration helper
     * @param Decider $featureSwitches Bolt Feature Switch decider
     */
    public function __construct(Config $config, Decider $featureSwitches)
    {
        $this->configHelper = $config;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * Returns comment for the provided order, stored in the configured field returned by
     *
     * @see \Bolt\Boltpay\Helper\Config::getOrderCommentField
     *
     * @param Order $order for which to retrieve the comment
     *
     * @return string|null
     */
    public function getCommentForOrder($order)
    {
        $commentField = $this->configHelper->getOrderCommentField($order->getStoreId());
        return $order->getData($commentField);
    }

    /**
     * Determines whether the order comment block should be displayed.
     * Requirements:
     * 1. M2_SHOW_ORDER_COMMENT_IN_ADMIN feature switch is enabled
     * 2. Order comment field is not empty
     * 3. Order payment method is Bolt
     *
     * @param Order $order being displayed in the admin
     *
     * @return bool true if the order comment block should be displayed, otherwise false
     *
     * @throws \Magento\Framework\Exception\LocalizedException if the feature switch is undefined
     */
    public function shouldDisplayForOrder($order)
    {
        return $this->featureSwitches->isShowOrderCommentInAdmin()
            && $this->getCommentForOrder($order)
            && $order->getPayment()
            && $order->getPayment()->getMethod() == Payment::METHOD_CODE;
    }
}
