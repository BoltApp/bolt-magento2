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

namespace Bolt\Boltpay\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote;

/**
 * Class Email.
 * Associate email to current quote
 * so the email notification system can react on abandoned cart event.
 */
class Email extends Action
{
    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var CustomerSession */
    private $customerSession;

    /** @var Bugsnag */
    private $bugsnag;

    /** @var CartHelper */
    private $cartHelper;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        Bugsnag $bugsnag,
        CartHelper $cartHelper
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
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

            if (!$this->cartHelper->validateEmail($email)) {
                throw new LocalizedException(__('Invalid email: %1', $email));
            }

            $quote->setCustomerEmail($email);
            $this->cartHelper->quoteResourceSave($quote);

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
