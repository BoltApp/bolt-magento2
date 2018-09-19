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

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Class ClearQuote
 *
 * @package Bolt\Boltpay\Plugin
 */
class ClearQuote
{
    /**
     * @param CheckoutSession $subject
     * @return CheckoutSession
     * @throws \Exception
     */
    public function afterClearQuote(CheckoutSession $subject)
    {
        $subject->setLoadInactive(false);
        $subject->replaceQuote($subject->getQuote()->save());

        return $subject;
    }
}
