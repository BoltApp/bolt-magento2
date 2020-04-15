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
namespace Bolt\Boltpay\Block\Adminhtml\Order\View\Tab;

/**
 * Class Info
 * @package Bolt\Boltpay\Block\Adminhtml\Order\View\Tab
 */
class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Tab\Info
{
    /**
     * @param $order
     * @return mixed
     */
    public function isBoltOrder($order){
        return ($order->getPayment() && $order->getPayment()->getMethod() === \Bolt\Boltpay\Model\Payment::METHOD_CODE);
    }
}
