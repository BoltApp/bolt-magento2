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

namespace Bolt\Boltpay\Controller\Shipping;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\Api\ShippingMethods;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use \Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\Helper\Geolocation;

/**
 * Class Prefetch.
 * Gets user location data from geolocation API.
 * Calls shipping estimation with the location data.
 * Shipping is pre-fetched and cached.
 */
class Prefetch extends Action
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ShippingMethods
     */
    private $shippingMethods;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Geolocation
     */
    private $geolocation;

    /**
     * @param Context $context
     * @param ShippingMethods $shippingMethods
     * @param CartHelper $cartHelper
     * @param Bugsnag $bugsnag
     * @param ConfigHelper $configHelper
     * @param CustomerSession $customerSession
     * @param Geolocation $geolocation
     */
    public function __construct(
        Context $context,
        ShippingMethods $shippingMethods,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        configHelper $configHelper,
        CustomerSession $customerSession,
        Geolocation $geolocation
    ) {
        parent::__construct($context);
        $this->shippingMethods = $shippingMethods;
        $this->cartHelper = $cartHelper;
        $this->bugsnag  = $bugsnag;
        $this->configHelper = $configHelper;
        $this->customerSession = $customerSession;
        $this->geolocation = $geolocation;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        try {
            if (!$this->configHelper->getPrefetchShipping()) {
                return;
            }

            $quoteId = $this->getRequest()->getParam('cartReference');

            /** @var Quote */
            $quote = $this->cartHelper->getQuoteById($quoteId);

            if (!$quote) {
                return;
            }

            ///////////////////////////////////////////////////////////////////////////
            // Prefetch Shipping and Tax for received location data
            ///////////////////////////////////////////////////////////////////////////
            $country  = $this->getRequest()->getParam('country');
            $region   = $this->getRequest()->getParam('region');
            $postcode = $this->getRequest()->getParam('postcode');

            if ($country && $region && $postcode) {
                $shipping_address = [
                    'country_code' => $country,
                    'postal_code'  => $postcode,
                    'region'       => $region,
                ];

                $this->shippingMethods->shippingEstimation($quote, $shipping_address);
            }
            ///////////////////////////////////////////////////////////////////////////

            ///////////////////////////////////////////////////////////////////////////
            // Prefetch Shipping and Tax for geolocated address
            ///////////////////////////////////////////////////////////////////////////
            if ($locationJson = $this->geolocation->getLocation($quote->getStoreId())) {
                $location = json_decode($locationJson);

                // at least country code and zip are needed for shipping estimation
                if ($location && isset($location->country_code) && isset($location->zip)) {
                    $shipping_address = [
                        'country_code' => $location->country_code,
                        'postal_code'  => $location->zip,
                        'region'       => isset($location->region_name) ? $location->region_name : "",
                        'locality'     => isset($location->city) ? $location->city : "",
                    ];
                    $this->shippingMethods->shippingEstimation($quote, $shipping_address);
                }
            }
            ///////////////////////////////////////////////////////////////////////////

            /////////////////////////////////////////////////////////////////////////////////
            // Prefetch Shipping and Tax for existing shipping address
            /////////////////////////////////////////////////////////////////////////////////
            $prefetchForStoredAddress = function ($shippingAddress) use ($quote) {

                if ($shippingAddress &&
                    ($country_code = $shippingAddress->getCountryId()) &&
                    ($postal_code  = $shippingAddress->getPostcode())
                ) {
                    $shipping_address = [
                        'country_code' => $country_code,
                        'postal_code'  => $postal_code,
                        'region'       => $shippingAddress->getRegion(),
                        'locality'     => $shippingAddress->getCity(),
                        'street_address1' => $shippingAddress->getStreetLine(1),
                        'street_address2' => $shippingAddress->getStreetLine(2),
                    ];

                    $this->shippingMethods->shippingEstimation($quote, $shipping_address);
                }
            };
            /////////////////////////////////////////////////////////////////////////////////

            /////////////////////////////////////////////////////////////////////////////////
            // Quote address may have already been set in checkout page.
            // Run the estimation for the quote shipping address.
            /////////////////////////////////////////////////////////////////////////////////
            $shippingAddress = $quote->getShippingAddress();
            $prefetchForStoredAddress($shippingAddress);
            /////////////////////////////////////////////////////////////////////////////////

            /////////////////////////////////////////////////////////////////////////////////
            // Run the estimation for logged in customer default shipping address.
            /////////////////////////////////////////////////////////////////////////////////
            $customer = $this->customerSession->getCustomer();

            if ($customer) {
                $shippingAddress = $customer->getDefaultShippingAddress();
                $prefetchForStoredAddress($shippingAddress);
            }
            /////////////////////////////////////////////////////////////////////////////////
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }
    }
}
