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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\MageVision\FreeShippingAdmin;

use Magento\Framework\App\State;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\Webapi\Rest\Request;

class MethodPlugin
{
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_resultMethodFactory;

    /**
     * @var State
     */
    protected $_appState;

    /**
     * @var Request
     */
    private $_restRequest;

    public function __construct(
        ResultFactory $rateResultFactory,
        MethodFactory $resultMethodFactory,
        State   $appState,
        Request $restRequest
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_resultMethodFactory = $resultMethodFactory;
        $this->_appState = $appState;
        $this->_restRequest  = $restRequest;
    }

    /**
     * Checks if we do calculation for bolt plugin
     *
     * @return bool
     */
    protected function isBoltCalculation()
    {
        if ($this->_appState->getAreaCode() !== 'webapi_rest') {
            return false;
        }
        if (!empty($this->_restRequest)) {
            $payload = $this->_restRequest->getContent();
            if (!empty($payload)) {
                $transaction = json_decode($payload);
                if (isset($transaction->order->cart->shipments[0]->reference)
                    && $transaction->order->cart->shipments[0]->reference == 'freeshippingadmin_freeshippingadmin'
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function afterCollectRates(
        \MageVision\FreeShippingAdmin\Model\Carrier\Method $subject,
        $result
    ) {
        // The method prohibits using FreeShippingAdmin shipping method for frontend orders, so it returns false
        // if user isn't logged as admin.
        // We need to check if calculation was called for Bolt and return rates if so.
        if ($result !== false) {
            return $result;
        }
        if (!$subject->getConfigFlag('active')) {
            return false;
        }

        if (!$this->isBoltCalculation()) {
            return false;
        }

        // Duplicate code from \MageVision\FreeShippingAdmin\Model\Carrier\Method
        $result = $this->_rateResultFactory->create();

        $method = $this->_resultMethodFactory->create();

        $method->setCarrier('freeshippingadmin');
        $method->setCarrierTitle($subject->getConfigData('title'));

        $method->setMethod('freeshippingadmin');
        $method->setMethodTitle($subject->getConfigData('name'));

        $method->setPrice('0.00');
        $method->setCost('0.00');

        $result->append($method);

        return $result;
    }
}
