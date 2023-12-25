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
 * @category Bolt
 * @package Bolt_Custom
 * @copyright Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Shineretrofits\ShippingMethod\Model\Carrier;

use Magento\Framework\App\State;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Shineretrofits\ShippingMethod\Model\Carrier\Customshipping as ShineretrofitsCustomshipping;

class CustomshippingPlugin
{    
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory|mixed
     */
    private $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory|mixed
     */
    private $_rateMethodFactory;

    /**
     * @var \Magento\Framework\App\State
     */
    private $_appState;

    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $_restRequest;

    public function __construct(
        State   $appState,
        Request $restRequest,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory
    ) {
        $this->_appState = $appState;
        $this->_restRequest = $restRequest;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
    }
    
    /**
     * Checks if we do calculation for Bolt plugin.
     *
     * @return bool
     */
    protected function isBoltCalculation()
    {
        if ($this->_appState->getAreaCode() !== \Magento\Framework\App\Area::AREA_WEBAPI_REST) {
            return false;
        }
        $payload = $this->_restRequest->getContent();
        if (!empty($payload)) {
            $transaction = json_decode($payload);
            if (isset($transaction->order->cart->shipments[0]->reference)
                && $transaction->order->cart->shipments[0]->reference == 'shineretrofits_shippingmethod_shineretrofits_shippingmethod'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The original method prohibits using Shineretrofits shipping method shipping method for webapi_rest,
     * so it returns false for Bolt backoffice order.
     * We need to check if calculation was called for Bolt and return rates if so.
     *
     * @param ShineretrofitsCustomshipping $subject
     * @param \Magento\Shipping\Model\Rate\Result|bool|null $result
     *
     * @return \Magento\Shipping\Model\Rate\Result|bool|null
     * @throws \Magento\Framework\Exception\LocalizedException if the area code is not set
     */
    public function afterCollectRates(
        ShineretrofitsCustomshipping $subject,
        $result
    ) {
        /** @var mixed $subject */
        if ($result !== false) {
            return $result;
        }       
        if (
		    !$subject->isActive()
		    || $this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_FRONTEND
		    || $this->_appState->getAreaCode() == 'webrest_api'
		    || !$this->isBoltCalculation()
		) {
		    return false;
		}

        $result = $this->_rateResultFactory->create();
        $shippingPrice = $subject->getConfigData('price');
        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($subject->getCarrierCode());
        $method->setCarrierTitle($subject->getConfigData('title'));
        $method->setMethod($subject->getCarrierCode());
        $method->setMethodTitle($subject->getConfigData('name'));
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        $result->append($method);
        return $result;
    }
}
