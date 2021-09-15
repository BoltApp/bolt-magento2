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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\MageWorx;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote;

class ShippingRules
{    
    /**
     * To setup customer group id for applying MageWorx shipping rules.
     *
     * @return void
     */
    public function beforeGetBoltpayOrderForBackofficeOrder($createAction)
    {
        // Before applying the valid rules, MageWorx_ShippingRules would fetch customer group id,
        // if param collect_shipping_rates exists in the request, it gets customer group id from quote,
        // if not, it gets customer group id from customer session.
        // For Bolt backoffice order, only the quote has proper customer group id.
        $createAction->getRequest()->setParam('collect_shipping_rates', '1');
    }
}
