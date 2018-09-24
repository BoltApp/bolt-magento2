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

use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Class LoginPlugin
 * Redirect to shopping cart page after cusromer has logged in from Ajax controller.
 *
 * @package Bolt\Boltpay\Plugin
 */
class LoginPlugin
{
    use LoginPluginTrait;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * LoginPlugin constructor.
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Redirect to shopping cart page upon successful login if the cart exists.
     *
     * @param \Magento\Customer\Controller\Ajax\Login $subject
     * @param \Magento\Framework\Controller\ResultInterface $result
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function afterExecute($subject, $result) {

        // Pass through the original result if the customer is not logged in or the cart is empty
        if (!$this->allowRedirect()) return $result;

        try {
            // Get and decode result object protected json property value
            $propGetter = \Closure::bind(function($prop){return $this->$prop;}, $result, $result);
            $json = $propGetter('json');
            $response = \Zend_Json::decode($json);

            // Sanity check. If result has an error flag set, pass the original result through unchainged
            if ($response['errors'] !== false) return $result;

            // No errors, user was successfully logged in
            // Generate new result by adding redirect url to the original data
            $response['redirectUrl'] = self::$shoppingCartPath;

            // Set the flag in session to auto-open Bolt checkout on redirected (shopping cart) page
            $this->setBoltOpenCheckoutFlag();

            $resultJson = $this->resultJsonFactory->create();
            return $resultJson->setData($response);

        } catch (\Exception $e) {
            // On any exception pass the original result through
            return $result;
        }
    }
}
