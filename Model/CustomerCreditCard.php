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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

/**
 * Class CustomerCreditCard
 * @package Bolt\Boltpay\Model
 */
class CustomerCreditCard extends AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'bolt_customer_credit_cards';

    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ConfigHelper $configHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param ApiHelper $apiHelper
     * @param CartHelper $cartHelper
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ConfigHelper $configHelper,
        DataObjectFactory $dataObjectFactory,
        ApiHelper $apiHelper,
        CartHelper $cartHelper,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->cartHelper = $cartHelper;
        $this->apiHelper = $apiHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->configHelper = $configHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }


    protected function _construct()
    {
        $this->_init('Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard');
    }

    /**
     * @return array|string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @param OrderModel $order
     * @return Response|int
     * @throws LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function recharge(OrderModel $order)
    {
        $apiKey = $this->configHelper->getApiKey();
        $orderCurrency = $order->getOrderCurrencyCode();
        //Request Data
        $requestData = $this->dataObjectFactory->create();
        $requestData->setApiData(
            [
                'consumer_id' => $this->getConsumerId(),
                'credit_card_id' => $this->getCreditCardId(),
                'cart' => [
                    "display_id" =>  $order->getIncrementId(),
                    'order_reference' => $order->getQuoteId(),
                    'total_amount' => CurrencyUtils::toMinor($order->getGrandTotal(), $orderCurrency),
                    'tax_amount' => CurrencyUtils::toMinor($order->getTaxAmount(), $orderCurrency),
                    'currency' => $orderCurrency,
                    'items' => $this->getOrderItems($order),
                    'shipments' => $this->getOrderShipments($order),
                    'discounts' => $this->getOrderDiscounts($order)
                ],
                'source' => 'direct_payments'
            ]
        );

        $requestData->setDynamicApiUrl(ApiHelper::API_AUTHORIZE_TRANSACTION);
        $requestData->setApiKey($apiKey);

        //Build Request
        $request = $this->apiHelper->buildRequest($requestData);
        $response = $this->apiHelper->sendRequest($request);

        if (empty($response)) {
            throw new LocalizedException(
                __('Bad payment response from boltpay')
            );
        }

        return $response;
    }

    /**
     * Get order discounts
     *
     * @param OrderModel $order
     * @return array
     * @throws \Exception
     */
    public function getOrderDiscounts(OrderModel $order)
    {
        $discountAmount = $order->getDiscountAmount() * -1;

        if ($discountAmount <= 0) {
            return [];
        }

        return [
            [
                'amount' => CurrencyUtils::toMinor($discountAmount, $order->getOrderCurrencyCode()),
                'code' => $order->getCouponCode(),
                'discount_category' => ($order->getCouponCode()) ? 'coupon' : 'automatic_promotion',
                'type' => ($order->getCouponCode()) ? 'coupon' : 'automatic_promotion'
            ]
        ];
    }

    /**
     * Get order shipments
     *
     * @param OrderModel $order
     * @return array
     * @throws \Exception
     */
    public function getOrderShipments(OrderModel $order)
    {
        if ($order->getIsVirtual() || !$order->getShippingAddress()) {
            return [];
        }

        return [
            [
                'cost' => CurrencyUtils::toMinor($order->getShippingAmount(), $order->getOrderCurrencyCode()),
                'carrier' => $order->getShippingDescription(),
                'shipping_address' => [
                    'first_name' => $order->getShippingAddress()->getFirstname(),
                    'last_name' => $order->getShippingAddress()->getLastname(),
                    'street_address1' => $order->getShippingAddress()->getStreetLine(1),
                    'street_address2' => $order->getShippingAddress()->getStreetLine(2),
                    'region' => $order->getShippingAddress()->getRegion(),
                    'region_code' => $order->getShippingAddress()->getRegion(),
                    'phone' => $order->getShippingAddress()->getTelephone(),
                    'country_code' => $order->getShippingAddress()->getCountryId(),
                    'postal_code' => $order->getShippingAddress()->getPostcode(),
                    'locality' => $order->getShippingAddress()->getCity(),
                    'email' => $order->getShippingAddress()->getEmail()
                ]
            ]
        ];
    }

    /**
     * Get order items
     *
     * @param OrderModel $order
     * @return array
     * @throws \Exception
     */
    public function getOrderItems(OrderModel $order)
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'total_amount' => CurrencyUtils::toMinor($item->getRowTotal(), $order->getOrderCurrencyCode()),
                'unit_price' => CurrencyUtils::toMinor($item->getPrice(), $order->getOrderCurrencyCode()),
                'quantity' => $item->getQtyOrdered()
            ];
        }
        return $items;
    }

    /**
     * @return object
     */
    public function getCardInfoObject()
    {
        $cardInfoArray = json_decode((string)$this->getCardInfo(), true);
        $cartInfo = $this->dataObjectFactory->create();
        $cartInfo->setData($cardInfoArray);

        return $cartInfo;
    }

    /**
     * @return string
     */
    public function getCardType()
    {
        return $this->getCardInfoObject()->getData('display_network');
    }

    /**
     * @return string
     */
    public function getCardLast4Digit()
    {
        return ($last4 = $this->getCardInfoObject()->getData('last4')) ? 'XXXX-'.$last4 : '';
    }

    /**
     * @param $customerId
     * @param $boltConsumerId
     * @param $boltCreditCardId
     * @param $cardInfo
     * @return $this
     */
    public function saveCreditCard($customerId, $boltConsumerId, $boltCreditCardId, $cardInfo)
    {
        $this->setCustomerId($customerId)
            ->setConsumerId($boltConsumerId)
            ->setCreditCardId($boltCreditCardId)
            ->setCardInfo(json_encode((array)$cardInfo))
            ->save();

        return $this;
    }
}
