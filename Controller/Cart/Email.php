<?php
/**
 *
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Prefetch.
 * Gets user location data from geolocation API.
 * Calls shipping estimation with the location data.
 * Shipping is prefetched and cached.
 *
 * @package Bolt\Boltpay\Controller\Shipping
 */
class Email extends Action
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param Bugsnag $bugsnag
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function execute()
    {
        try {

            /** @var Quote */
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('Quote does not exist.'));
            }

            $email = $this->getRequest()->getParam('email');

            if (!$email) {
                throw new LocalizedException(__('No email received.'));
            }

            $quote->setCustomerEmail($email)->save();

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
