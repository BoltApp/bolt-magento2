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

namespace Bolt\Boltpay\Plugin;

use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;

/**
 * Class MirasvitCreditQuotePaymentImportDataBeforePlugin
 * Support Store Credit for admin order
 *
 * @package Bolt\Boltpay\Plugin
 */
class MirasvitCreditQuotePaymentImportDataBeforePlugin
{
    /**
     * @var DiscountHelper
     */
    private $discountHelper;

    /**
     * MirasvitCreditQuotePaymentImportDataBeforePlugin constructor.
     *
     * @param DiscountHelper $discountHelper
     */
    public function __construct(DiscountHelper $discountHelper)
    {
        $this->discountHelper = $discountHelper;
    }

    /**
     * @param ObserverInterface $subject
     * @param Observer          $observer
     *
     * @return mixed
     */
    public function beforeExecute(ObserverInterface $subject, Observer $observer)
    {
        if ($this->discountHelper->isMirasvitAdminQuoteUsingCreditObserver($observer)) {
            $observer->getEvent()->getInput()->setUseCredit(true);
        }

        return [$observer];
    }
}
