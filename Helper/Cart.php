<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Response;
use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product as ProductModel;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Zend_Http_Client_Exception;
use Bolt\Boltpay\Helper\Log as LogHelper;

/**
 * Boltpay Cart helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cart extends AbstractHelper
{
	/** @var CheckoutSession */
	protected $checkoutSession;

	/** @var CheckoutSession */
	protected $customerSession;

	/**
     * @var ImageHelper
     */
    protected $imageHelper;

    /**
     * @var ApiHelper
     */
    protected $apiHelper;

    /**
     * @var ProductModel
     */
    protected $productModel;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

	/**
	 * @var LogHelper
	 */
	protected $logHelper;

	// Billing / shipping address fields that are required when the address data is sent to Bolt.
	private $required_address_fields = [
		'first_name',
		'last_name',
		'street_address1',
		'locality',
		'region',
		'postal_code',
		'country_code',
		'email',
	];

	///////////////////////////////////////////////////////
	// Store discount types, internal and 3rd party.
	// Can appear as keys in Quote::getTotals result array.
	///////////////////////////////////////////////////////
	private $discount_types = array(
		'reward',
		'giftvoucheraftertax',
	);
	///////////////////////////////////////////////////////

	/**
	 * @param Context           $context
	 * @param CheckoutSession   $checkoutSession
	 * @param ImageHelper       $imageHelper
	 * @param ProductModel      $productModel
	 * @param ApiHelper         $apiHelper
	 * @param ConfigHelper      $configHelper
	 * @param Session           $customerSession
	 * @param LogHelper         $logHelper
	 *
	 * @codeCoverageIgnore
	 */
    public function __construct(
        Context         $context,
        CheckoutSession $checkoutSession,
        ImageHelper     $imageHelper,
        ProductModel    $productModel,
	    ApiHelper       $apiHelper,
	    ConfigHelper    $configHelper,
	    Session         $customerSession,
	    LogHelper       $logHelper
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->imageHelper     = $imageHelper;
        $this->productModel    = $productModel;
        $this->apiHelper       = $apiHelper;
	    $this->configHelper    = $configHelper;
	    $this->customerSession = $customerSession;
	    $this->logHelper       = $logHelper;
    }

	/**
	 * Create order on bolt
	 *
	 * @param bool $payment_only               flag that represents the type of checkout
	 * @param string $place_order_payload      additional data collected from the (one page checkout) page,
	 *                                         i.e. billing address to be saved with the order
	 *
	 * @return Response|int
	 * @throws Exception
	 * @throws LocalizedException
	 * @throws Zend_Http_Client_Exception
	 */
    public function getBoltpayOrder($payment_only, $place_order_payload)
    {
        //Get cart data
        $cartData = $this->getCartData($payment_only, $place_order_payload);
	    $apiKey   = $this->configHelper->getApiKey();

        //Request Data
        $requestData = new DataObject();
        $requestData->setApiData($cartData);
        $requestData->setDynamicApiUrl(ApiHelper::API_CREATE_ORDER);
        $requestData->setApiKey($apiKey);

        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);
        $result  = $this->apiHelper->sendRequest($request);
        return $result;
    }

	/**
	 * Sign a payload using the Bolt endpoint
	 *
	 * @param array $signRequest  payload to sign
	 *
	 * @return Response|int
	 */
	private function getSignResponse($signRequest) {
	    $apiKey = $this->configHelper->getApiKey();

	    //Request Data
	    $requestData = new DataObject();
	    $requestData->setApiData($signRequest);
	    $requestData->setDynamicApiUrl(ApiHelper::API_SIGN);
	    $requestData->setApiKey($apiKey);

	    $request = $this->apiHelper->buildRequest($requestData);
	    try {
		    $result = $this->apiHelper->sendRequest($request);
	    } catch ( \Exception $e ) {
	        return null;
	    }
	    return $result;
    }

	/**
	 * Get the hints data for checkout
	 *
	 * @param string $place_order_payload      additional data collected from the (one page checkout) page,
	 *                                         i.e. billing address to be saved with the order
	 *
	 * @return array
	 */
	public function getHints($place_order_payload)
	{
		$quote = $this->checkoutSession->getQuote();
		$shippingAddress = $quote->getShippingAddress();

		if ($place_order_payload) {
			$place_order_payload = @json_decode($place_order_payload);
			$email = @$place_order_payload->email;
		}

		$shippingStreetAddress = $shippingAddress->getStreet();
		$hints = [
			'prefill' => [
				'firstName'    => $shippingAddress->getFirstname(),
				'lastName'     => $shippingAddress->getLastname(),
				'email'        => $shippingAddress->getEmail() ?: @$email,
				'phone'        => $shippingAddress->getTelephone(),
				'addressLine1' => array_key_exists(0, $shippingStreetAddress) ? $shippingStreetAddress[0] : '',
				'addressLine2' => array_key_exists(1, $shippingStreetAddress) ? $shippingStreetAddress[1] : '',
				'city'         => $shippingAddress->getCity(),
				'state'        => $shippingAddress->getRegion(),
				'zip'          => $shippingAddress->getPostcode(),
				'country'      => $shippingAddress->getCountryId(),
			]
		];

		foreach ($hints['prefill'] as $name => $value) {
			if (empty($value)) {
				unset($hints['prefill'][$name]);
			}
		}

		if ($this->customerSession->isLoggedIn()) {
			// Customer is logged in
			$signRequest = array(
				'merchant_user_id' => $this->customerSession->getCustomer()->getId(),
			);
			$signResponse = $this->getSignResponse($signRequest)->getResponse();

			if ($signResponse != null) {
				$hints['signed_merchant_user_id'] = array(
					"merchant_user_id" => $signResponse->merchant_user_id,
					"signature"        => $signResponse->signature,
					"nonce"            => $signResponse->nonce,
				);
			}

			$hints['prefill']['email'] = @$hints['prefill']['email'] ?: $this->customerSession->getCustomer()->getEmail();
		}

		return $hints;
	}

	/**
	 * Get cart data.
	 * The reference of total methods: dev/tests/api-functional/testsuite/Magento/Quote/Api/CartTotalRepositoryTest.php
	 *
	 * @param bool $payment_only               flag that represents the type of checkout
	 * @param string $place_order_payload      additional data collected from the (one page checkout) page,
	 *                                         i.e. billing address to be saved with the order
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getCartData($payment_only, $place_order_payload)
	{
		$quote = $this->checkoutSession->getQuote();

		if (null === $quote->getId()) {
			throw new LocalizedException(__('Non existing session quote object.'));
		}

		$quote->collectTotals();
		$totals = $quote->getTotals();

		$this->logHelper->addInfoLog(var_export($totals, 1));

		$cart = [];

		// Order reference id
		$cart['order_reference'] = $quote->getId();

		//Set display_id as reserve order id
		$cart['display_id'] = $this->setReserveOrderId();

		//Currency
		$cart['currency'] = $quote->getQuoteCurrencyCode();

		// Get array of all items what can be display directly
		$items = $quote->getAllVisibleItems();

		foreach($items as $item) {
			$product = [];
			$productId = $item->getProductId();

			$product['reference']    = $productId;
			$product['name']         = $item->getName();
			$product['description']  = $item->getDescription();
			$product['total_amount'] = $this->getRoundAmount($item->getCalculationPrice() * $item->getQty());
			$product['unit_price']   = $this->getRoundAmount($item->getCalculationPrice());
			$product['quantity']     = $item->getQty();
			$product['sku']          = $item->getSku();
			//Get product Image
			$_product = $this->productModel->load($productId);
			$product['image_url'] = $this->imageHelper->init($_product,'category_page_list')->getUrl();
			//Add product to items array
			$cart['items'][] = $product;
		}

		// Billing address
		$billingAddress       = $quote->getBillingAddress();
		$billingStreetAddress = $billingAddress->getStreet();
		$cart['billing_address'] = [
			'first_name' 	  => $billingAddress->getFirstname(),
			'last_name'       => $billingAddress->getLastname(),
			'company'         => $billingAddress->getCompany(),
			'phone'           => $billingAddress->getTelephone(),
			'email'           => $billingAddress->getEmail(),
			'street_address1' => array_key_exists(0, $billingStreetAddress) ? $billingStreetAddress[0] : '',
			'street_address2' => array_key_exists(1, $billingStreetAddress) ? $billingStreetAddress[1] : '',
			'locality'        => $billingAddress->getCity(),
			'region'          => $billingAddress->getRegion(),
			'postal_code'     => $billingAddress->getPostcode(),
			'country_code'    => $billingAddress->getCountryId(),
		];

		$shippingAddress = $quote->getShippingAddress();

		// additional data sent, i.e. billing address from checkout page
		if ($place_order_payload) {

			$place_order_payload = json_decode($place_order_payload);

			$email                = @$place_order_payload->email;
			$billingAddress       = @$place_order_payload->billingAddress;
			$billingStreetAddress = (array)@$billingAddress->street;

			if ($billingAddress) {
				$cart['billing_address'] = [
					'first_name'      => @$billingAddress->firstname,
					'last_name'       => @$billingAddress->lastname,
					'company'         => @$billingAddress->company,
					'phone'           => @$billingAddress->telephone,
					'street_address1' => array_key_exists(0, $billingStreetAddress) ? $billingStreetAddress[0] : '',
					'street_address2' => array_key_exists(1, $billingStreetAddress) ? $billingStreetAddress[1] : '',
					'locality'        => @$billingAddress->city,
					'region'          => @$billingAddress->region,
					'postal_code'     => @$billingAddress->postcode,
					'country_code'    => @$billingAddress->countryId,
				];
			}

			if ($email) {
				$cart['billing_address']['email'] = $email;
			}
		}

		if ($payment_only) {

			// payment only checkout, include shipments, tax and grand total

			// Shipping address
			$shippingStreetAddress = $shippingAddress->getStreet();
			$shipping_address = [
				'first_name'      => $shippingAddress->getFirstname(),
				'last_name'       => $shippingAddress->getLastname(),
				'company'         => $shippingAddress->getCompany(),
				'phone'           => $shippingAddress->getTelephone(),
				'email'           => $shippingAddress->getEmail(),
				'street_address1' => array_key_exists(0, $shippingStreetAddress) ? $shippingStreetAddress[0] : '',
				'street_address2' => array_key_exists(1, $shippingStreetAddress) ? $shippingStreetAddress[1] : '',
				'locality'        => $shippingAddress->getCity(),
				'region'          => $shippingAddress->getRegion(),
				'postal_code'     => $shippingAddress->getPostcode(),
				'country_code'    => $shippingAddress->getCountryId(),
			];

			if (@$email) {
				$shipping_address['email'] = $email;
			}

			foreach ($this->required_address_fields as $field) {
				if (empty($shipping_address[$field])) {
					unset($shipping_address);
					break;
				}
			}

			$cart['shipments'] = @$shipping_address ? [[
				'cost'             => $this->getRoundAmount($shippingAddress->getShippingAmount()),
				'tax_amount'       => $this->getRoundAmount($shippingAddress->getShippingTaxAmount()),
				'shipping_address' => $shipping_address,
				'service'          => $shippingAddress->getShippingDescription(),
			]] : null;

			$totalAmount = $quote->getGrandTotal();
			$taxAmount   = $this->getRoundAmount($shippingAddress->getTaxAmount());

		} else {

			// multi-step checkout, get subtotal, no tax
			$totalAmount = $quote->getSubtotalWithDiscount();
			$taxAmount   = 0;
		}

		// unset billing if not all required fields are present
		foreach ($this->required_address_fields as $field) {
			if (empty($cart['billing_address'][$field])) {
				unset($cart['billing_address']);
				break;
			}
		}

		// add discount data
		$cart['discounts'] = [];

		if ($amount = @$shippingAddress->getDiscountAmount()) {
			$cart['discounts'][] = [
				'description' => __('Discount ') . $shippingAddress->getDiscountDescription(),
				'amount'      => abs($this->getRoundAmount($amount)),
			];
		}

		foreach ($this->discount_types as $discount) {

			if (@$totals[$discount] && $amount = @$totals[$discount]->getValue()) {

				$cart['discounts'][] = [
					'description' => @$totals[$discount]->getTitle(),
					'amount'      => abs($this->getRoundAmount($amount))
				];

				if (!$payment_only) {
					$totalAmount -= abs($amount);
				}
			}
		}

		if ($amount = @$quote->getCustomerBalanceAmountUsed()) {
			$cart['discounts'][] = [
				'description' => __('%1 Store Credit', $amount),
				'amount'      => abs($this->getRoundAmount($amount))
			];
			if (!$payment_only) {
				$totalAmount -= abs($amount);
			}
		}

		$cart['total_amount'] = $this->getRoundAmount($totalAmount);
		$cart['tax_amount']   = $taxAmount;

		$this->logHelper->addInfoLog(json_encode($cart, JSON_PRETTY_PRINT));

		return ['cart' => $cart];
	}

	/**
	 * Reserve order id for the quote
	 *
	 * @return  string
	 * @throws Exception
	 */
    public function setReserveOrderId()
    {
        /** @var Quote  */
        $quote = $this->checkoutSession->getQuote();
        $quote->reserveOrderId()->save();
        $reserveOrderId = $quote->getReservedOrderId();
        return $reserveOrderId;
    }

    /**
     * Round amount helper
     *
     * @return  int
     */
    public function getRoundAmount($amount)
    {
	    return (int)round($amount * 100);
    }
}
