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
use \Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Email.
 * Associate email to current quote
 * so the email notiffication system can react on abandoned cart event.
 *
 * @package Bolt\Boltpay\Controller\Cart
 */
class Email extends Action
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var CustomerSession */
    private $customerSession;

    /** @var Bugsnag */
    private $bugsnag;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param Bugsnag $bugsnag
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function execute()
    {
        try {

            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('Quote does not exist.'));
            }

            $email = $this->customerSession->isLoggedIn() ?
                $this->customerSession->getCustomer()->getEmail() :
                $this->getRequest()->getParam('email');

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
