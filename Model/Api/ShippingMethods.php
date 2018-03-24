<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ShippingOptionsInterface;
use Bolt\Boltpay\Api\ShippingMethodsInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Directory\Model\Region as RegionModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Api\Data\ShippingOptionsInterfaceFactory;
use Bolt\Boltpay\Api\Data\ShippingTaxInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Bolt\Boltpay\Api\Data\ShippingOptionInterface;
use Bolt\Boltpay\Api\Data\ShippingOptionInterfaceFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Response;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class ShippingMethods
 * Shipping and Tax hook endpoint. Get shipping methods using shipping address and cart details
 *
 * @package Bolt\Boltpay\Model\Api
 */
class ShippingMethods implements ShippingMethodsInterface
{
	/**
	 * @var HookHelper
	 */
	protected $hookHelper;

	/**
	 * @var CartHelper
	 */
	protected $cartHelper;

	/**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var RegionModel
     */
    protected $regionModel;

	/**
	 * @var ShippingOptionsInterfaceFactory
	 */
	protected $shippingOptionsInterfaceFactory;

	/**
	 * @var ShippingTaxInterfaceFactory
	 */
	protected $shippingTaxInterfaceFactory;

	/**
	 * @var TotalsCollector
	 */
	protected $totalsCollector;

	/**
	 * Shipping method converter
	 *
	 * @var ShippingMethodConverter
	 */
	protected $converter;

	/**
	 * @var ShippingOptionInterfaceFactory
	 */
	protected $shippingOptionInterfaceFactory;

	/**
	 * @var Bugsnag
	 */
	protected $bugsnag;

	/**
	 * @var LogHelper
	 */
	protected $logHelper;

	/**
	 * @var Response
	 */
	protected $response;

	/**
	 * @var ConfigHelper
	 */
	protected $configHelper;

	/**
	 *
	 * @param HookHelper $hookHelper
	 * @param QuoteFactory $quoteFactory
	 * @param RegionModel $regionModel
	 * @param ShippingOptionsInterfaceFactory $shippingOptionsInterfaceFactory
	 * @param ShippingTaxInterfaceFactory $shippingTaxInterfaceFactory
	 * @param CartHelper $cartHelper
	 * @param TotalsCollector $totalsCollector
	 * @param ShippingMethodConverter $converter
	 * @param ShippingOptionInterfaceFactory $shippingOptionInterfaceFactory
	 * @param Bugsnag $bugsnag
	 * @param LogHelper $logHelper
	 * @param Response $response
	 * @param Config $configHelper
	 */
    public function __construct(
	    HookHelper                      $hookHelper,
        QuoteFactory                    $quoteFactory,
        RegionModel                     $regionModel,
	    ShippingOptionsInterfaceFactory $shippingOptionsInterfaceFactory,
        ShippingTaxInterfaceFactory     $shippingTaxInterfaceFactory,
	    CartHelper                      $cartHelper,
	    TotalsCollector                 $totalsCollector,
	    ShippingMethodConverter         $converter,
	    ShippingOptionInterfaceFactory  $shippingOptionInterfaceFactory,
	    Bugsnag                         $bugsnag,
	    LogHelper                       $logHelper,
	    Response                        $response,
	    ConfigHelper                    $configHelper
    ){
	    $this->hookHelper                      = $hookHelper;
	    $this->cartHelper                      = $cartHelper;
        $this->quoteFactory                    = $quoteFactory;
        $this->regionModel                     = $regionModel;
        $this->shippingOptionsInterfaceFactory = $shippingOptionsInterfaceFactory;
	    $this->shippingTaxInterfaceFactory     = $shippingTaxInterfaceFactory;
	    $this->totalsCollector                 = $totalsCollector;
	    $this->converter                       = $converter;
	    $this->shippingOptionInterfaceFactory  = $shippingOptionInterfaceFactory;
	    $this->bugsnag                         = $bugsnag;
	    $this->logHelper                       = $logHelper;
	    $this->response                        = $response;
	    $this->configHelper                    = $configHelper;
    }

	/**
	 * Get all available shipping methods.
	 *
	 * @api
	 *
	 * @param array $cart cart details
	 * @param array $shipping_address shipping address
	 *
	 * @return ShippingOptionsInterface
	 * @throws \Exception
	 */
    public function getShippingMethods($cart, $shipping_address)
    {
	    try {
		    $this->response->setHeader('User-Agent', 'BoltPay/Magento-'.$this->configHelper->getStoreVersion());
		    $this->response->setHeader('X-Bolt-Plugin-Version', $this->configHelper->getModuleVersion());

	        $this->hookHelper->verifyWebhook();

	        $incrementId = $cart['display_id'];

			// Get quote from increment id
	        $quote   = $this->quoteFactory->create()->load($incrementId, 'reserved_order_id');
	        $quoteId = $quote->getId();

	        if (empty($quoteId)) {
	            throw new LocalizedException(
	                __('Invalid display_id :%1.', $incrementId)
	            );
	        }

	         //Get region id
	        $region = $this->regionModel->loadByName($shipping_address['region'], $shipping_address['country_code']);

	        $shipping_address = [
	            'firstname'  => $shipping_address['first_name'],
	            'lastname'   => $shipping_address['last_name'],
	            'street'     => $shipping_address['street_address1'],
	            'city'       => $shipping_address['locality'],
	            'country_id' => $shipping_address['country_code'],
	            'region'     => $shipping_address['region'],
	            'postcode'   => $shipping_address['postal_code'],
	            'telephone'  => $shipping_address['phone'],
	            'region_id'  => $region->getId(),
	        ];

	        //save shipping address in quote
		    $shippingAddress = $quote->getShippingAddress();

		    $shippingAddress->addData($shipping_address);
		    $shippingAddress->setCollectShippingRates(true);

		    $this->totalsCollector->collectAddressTotals($quote, $shippingAddress);

	        $shippingMethods      = $this->getShippingOptions($quote);
	        $shippingOptionsModel = $this->shippingOptionsInterfaceFactory->create();
		    $shippingOptionsModel->setShippingOptions($shippingMethods);

		    $shippingTaxModel = $this->shippingTaxInterfaceFactory->create();

		    $shippingTaxModel->setAmount($this->cartHelper->getRoundAmount($shippingAddress->getBaseTaxAmount()));
		    $shippingOptionsModel->setTaxResult($shippingTaxModel);

		    //$this->logHelper->addInfoLog(var_export($shippingOptionsModel, 1));

	        return $shippingOptionsModel;
	    } catch ( \Exception $e ) {
		    $this->bugsnag->notifyException($e);
		    throw $e;
	    }
    }

	/**
	 * Save shipping address in quote
	 *
	 * @param Quote $quote
	 *
	 * @return ShippingOptionInterface[]
	 */
	private function getShippingOptions($quote)
	{
		$output = [];

		$shippingAddress = $quote->getShippingAddress();
		$shippingRates   = $shippingAddress->getGroupedAllShippingRates();

		foreach ($shippingRates as $carrierRates) {
			foreach ($carrierRates as $rate) {
				$output[] = $this->converter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
			}
		}

		$shippingMethods = [];

		foreach ($output as $shippingMethod) {

			$service    = $shippingMethod->getCarrierTitle() . " - " . $shippingMethod->getMethodTitle();
			$carrier    = $shippingMethod->getCarrierCode() . "-" . $shippingMethod->getMethodCode();
			$cost       = $this->cartHelper->getRoundAmount($shippingMethod->getPriceInclTax());
			$tax_amount = $this->cartHelper->getRoundAmount($shippingMethod->getPriceInclTax() - $shippingMethod->getPriceExclTax());

			$shippingMethods[] = $this->shippingOptionInterfaceFactory
				->create()
				->setService($service)
				->setCost($cost)
				->setCarrier($carrier)
				->setTaxAmount($tax_amount);
		}
		return $shippingMethods;
	}
}
