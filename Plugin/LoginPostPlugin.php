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
 * Class LoginPostPlugin
 * Redirect to shopping cart page after customer has logged in from Account controller.
 *
 * @package Bolt\Boltpay\Plugin
 */
class LoginPostPlugin extends AbstractLoginPlugin
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

            // Set the flag in session to auto-open Bolt checkout on redirected (shopping cart) page
            $this->setBoltInitiateCheckout();

            // Redirect to shopping cart
            return $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath(self::SHOPPING_CART_PATH);
        } catch (\Exception $e) {
            // On any exception pass the original result through
            $this->notifyException($e);
            return $result;
        }
    }
}
