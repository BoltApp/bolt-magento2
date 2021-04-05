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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ViewModel;

use Bolt\Boltpay\Helper\Config;
use Magento\Sales\Model\Order;

class OrderComment implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * OrderComment constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->configHelper = $config;
    }

    /**
     * @param Order $order
     */
    public function getCommentForOrder($order)
    {
        $commentField = $this->configHelper->getOrderCommentField($order->getStoreId());
        return $order->getData($commentField);
    }
}
