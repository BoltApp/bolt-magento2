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

use Magento\Checkout\Model\Session;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class RestoreQuotePlugin
 *
 * @package Bolt\Boltpay\Plugin
 */
class RestoreQuotePlugin
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * RestoreQuotePlugin constructor.
     * @param Session $checkoutSession
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Session $checkoutSession,
        Bugsnag $bugsnag
    ) {
        $this->bugsnag = $bugsnag;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param Session $subject
     * @param callable $proceed
     * @return bool
     */
    public function aroundRestoreQuote(
        Session $subject,
        callable $proceed
    ) {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order->getPayment() && $order->getPayment()->getMethod() == \Bolt\Boltpay\Model\Payment::METHOD_CODE) {
            $quoteId = $order->getQuoteId();
            $this->bugsnag->notifyError('Ignore restoring quote if payment method is Boltpay', "Quote Id: {$quoteId}");
            return false;
        }

        return $proceed();
    }
}
