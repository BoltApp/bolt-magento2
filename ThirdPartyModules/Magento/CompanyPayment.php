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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

use Bolt\Boltpay\Model\Payment;
use Magento\Framework\App\Http\Context;

class CompanyPayment
{
    /**
     * @var Payment
     */
    private $boltPayment;
    
    /**
     * @var Context
     */
    private $httpContext;
    
    /**
     * @param Payment $boltPayment
     */
    public function __construct(
        Payment $boltPayment,
        Context $httpContext
    ) {
        $this->boltPayment = $boltPayment;
        $this->httpContext = $httpContext;
    }
    
    /**
     * @param bool $result
     * @param Magento\CompanyPayment\Model\Payment\Checks\CanUseForCompany $canUseForCompany
     * @param Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function filterShouldDisableBoltCheckout(
        $result,
        $canUseForCompany,
        $quote
    ) {
        if (!$quote->getCustomerId() && ($customerId = $this->httpContext->getValue('customer_id_bolt'))) {
            $quote->setCustomerId($customerId);
        }
        if (!$canUseForCompany->isApplicable($this->boltPayment, $quote)) {
            $result = true;
        }
        return $result;
    }
    
    /**
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Http\Context $httpContext
     */
    public function shouldDisableBoltCheckout(
        $customerSession,
        $httpContext
    ) {
        if ($customerId = $customerSession->getCustomerId()) {
            $this->httpContext->setValue(
                'customer_id_bolt',
                $customerId,
                false
            );
        }
    }
}
