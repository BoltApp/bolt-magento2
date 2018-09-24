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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin;

use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Class LoginPostPlugin
 * Redirect to shopping cart page after cusromer has logged in from Account controller.
 *
 * @package Bolt\Boltpay\Plugin
 */
class LoginPostPlugin
{
    use LoginPluginTrait;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * LoginPostPlugin constructor.
     * @param RedirectFactory $resultRedirectFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Redirect to shopping cart page upon successful login if the cart exists.
     *
     * @param \Magento\Customer\Controller\Account\LoginPost $subject
     * @param \Magento\Framework\Controller\ResultInterface $result
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function afterExecute($subject, $result) {

        // Pass through the original result if the customer is not logged in or the cart is empty
        if (!$this->allowRedirect()) return $result;

        // Set the flag in session to auto-open Bolt checkout on redirected (shopping cart) page
        $this->setBoltOpenCheckoutFlag();

        // Redirect to shopping cart
        return $this->resultRedirectFactory->create()
            ->setPath(self::$shoppingCartPath);
    }
}
