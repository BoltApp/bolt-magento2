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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Cron\DeactivateQuote;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Class DeactivateQuoteTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class DeactivateQuoteTest extends BoltTestCase
{
    private $objectManager;
    private $deactivateQuote;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->deactivateQuote = $this->objectManager->create(DeactivateQuote::class);
    }

    /**
     * @test
     */
    public function execute()
    {
        $quote = TestUtils::createQuote(['is_active' => true]);
        $quoteId = $quote->getId();
        $payment = $this->objectManager->create(Payment::class);
        $payment->setMethod(\Bolt\Boltpay\Model\Payment::METHOD_CODE);

        $paymentData = [
            'transaction_reference' => 'XXXXX',
            'transaction_state' => 'cc_payment:pending',
        ];
        $payment->setAdditionalInformation(array_merge((array)$payment->getAdditionalInformation(), $paymentData));

        $order = TestUtils::createDumpyOrder(['quote_id' => $quoteId], [], [], Order::STATE_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $payment);

        $this->deactivateQuote->execute();

        self::assertEquals('0',$this->objectManager->create(Quote::class)->loadByIdWithoutStore($quoteId)->getIsActive());
        TestUtils::cleanupSharedFixtures([$order]);
    }


}
