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

/**
 * Class LoginPlugin
 * Redirect to shopping cart page after customer has logged in from Ajax controller.
 *
 * @package Bolt\Boltpay\Plugin
 */
class LoginPlugin extends AbstractLoginPlugin
{
    /**
     * Redirect to shopping cart page upon successful login if the cart exists.
     *
     * @param Action $subject
     * @param ResultInterface $result
     *
     * @return ResultInterface
     */
    public function afterExecute($subject, $result)
    {

        try {
            // Pass through the original result if the customer is not logged in or the cart is empty
            if (!$this->shouldRedirectToCartPage()) {
                return $result;
            }

            // Get and decode result object protected json property value
            $propGetter = \Closure::bind(function ($prop) {
                return $this->$prop;
            }, $result, $result);
            $json = $propGetter('json');
            $response = \Zend_Json::decode($json);

            // Sanity check. If result has an error flag set, pass the original result through unchainged
            if ($response['errors'] !== false) {
                return $result;
            }

            // No errors, user was successfully logged in
            // Generate new result by adding redirect url to the original data
            $response['redirectUrl'] = '/' . self::SHOPPING_CART_PATH;

            // Set the flag in session to auto-open Bolt checkout on redirected (shopping cart) page
            $this->setBoltInitiateCheckout();

            return $this->resultFactory
                ->create(ResultFactory::TYPE_JSON)
                ->setData($response);
        } catch (\Exception $e) {
            // On any exception pass the original result through
            $this->notifyException($e);
            return $result;
        }
    }
}
