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

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Bugsnag;

abstract class AbstractLoginPlugin
{
    const SHOPPING_CART_PATH = 'checkout/cart';

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * ResultFactory
     */
    protected $resultFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * AbstractLoginPlugin constructor.
     *
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param ResultFactory $resultFactory
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        ResultFactory $resultFactory,
        Bugsnag $bugsnag
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->resultFactory = $resultFactory;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Redirect to shopping cart page upon successful login if the cart exists.
     *
     * @param Action $subject
     * @param ResultInterface $result
     *
     * @return ResultInterface
     */
    abstract public function afterExecute($subject, $result);

    /**
     * @return bool
     */
    protected function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * @return bool
     */
    protected function hasCart()
    {
        return $this->checkoutSession->hasQuote() && count($this->checkoutSession->getQuote()->getAllVisibleItems()) > 0;
    }

    /**
     * @return bool
     */
    protected function shouldRedirectToCartPage()
    {
        return $this->isCustomerLoggedIn() && $this->hasCart();
    }

    /**
     * @return void
     */
    protected function setBoltInitiateCheckout()
    {
        $this->checkoutSession->setBoltInitiateCheckout(true);
    }

    /**
     * @param \Exception $e
     * @return void
     */
    protected function notifyException($e)
    {
        $this->bugsnag->notifyException($e);
    }
}
