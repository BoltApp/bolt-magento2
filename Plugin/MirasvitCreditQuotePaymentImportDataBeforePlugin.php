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

namespace Bolt\Boltpay\Plugin;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Class MirasvitCreditQuotePaymentImportDataBeforePlugin
 * Support Store Credit for admin order
 */
class MirasvitCreditQuotePaymentImportDataBeforePlugin
{
    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * MirasvitCreditQuotePaymentImportDataBeforePlugin constructor.
     *
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(EventsForThirdPartyModules $eventsForThirdPartyModules)
    {
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }

    /**
     * @param ObserverInterface $subject
     * @param Observer          $observer
     *
     * @return mixed
     */
    public function beforeExecute(ObserverInterface $subject, Observer $observer)
    {
        if ($this->eventsForThirdPartyModules->runFilter("checkMirasvitCreditAdminQuoteUsed", false, $observer)) {
            $observer->getEvent()->getInput()->setUseCredit(true);
        }

        return [$observer];
    }
}
