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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Model\CustomerCreditCard;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Klarna\Core\Helper\ConfigHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Framework\DataObjectFactory;

class CustomerCreditCardTest extends BoltTestCase
{
    const QUOTE_ID = '111';
    const QUOTE_GRAND_TOTAL = '112';
    const ORDER_CURRENCY_CODE = 'USD';
    const CONSUMER_ID = '113';
    const CREDIT_CARD_ID = '114';
    const CARD_INFO = '{"id":"CAfe9tP97CMXs","last4":"1111","display_network":"Visa"}';
    const ENTITY_ID = '1';
    const CUSTOMER_ID = '1111';
    const INCREMENT_ID = '1111';

    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCard
     */
    private $mockCustomerCreditCard;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeId;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteId;

    /**
     * @var CustomerCreditCard
     */
    private $newCustomerCreditCard;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    private $customerId;

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->mockCustomerCreditCard = $this->objectManager->create(CustomerCreditCardFactory::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
        $customer = TestUtils::createCustomer($this->websiteId, $this->storeId, [
            "street_address1" => "street",
            "street_address2" => "",
            "locality" => "Los Angeles",
            "region" => "California",
            'region_code' => 'CA',
            'region_id' => '12',
            "postal_code" => "11111",
            "country_code" => "US",
            "country" => "United States",
            "name" => "lastname firstname",
            "first_name" => "firstname",
            "last_name" => "lastname",
            "phone_number" => "11111111",
            "email_address" => "john@bolt.com",
        ]);
        $this->customerId = $customer->getId();

        $this->newCustomerCreditCard = $this->mockCustomerCreditCard->create()
            ->setCustomerId($this->customerId)
            ->setConsumerId('xxxx')
            ->setCreditCardId('xxxxxx')
            ->setCardInfo(self::CARD_INFO)
            ->save();
        $this->dataObjectFactory = $this->objectManager->create(DataObjectFactory::class);
    }

    /**
     * @test
     */
    public function recharge_withException()
    {
        $order = $this->createPartialMock(
            Order::class,
            [
                'getQuoteId',
                'getOrderCurrencyCode',
                'getGrandTotal',
                'getIncrementId'
            ]
        );
        $order->expects(self::any())->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $order->expects(self::once())->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $order->expects(self::once())->method('getGrandTotal')->willReturn(self::QUOTE_GRAND_TOTAL);
        $order->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::ORDER_CURRENCY_CODE);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Authentication error. Authorization required.');
        $this->mockCustomerCreditCard->create()->recharge($order);
    }

    /**
     * @test
     */
    public function recharge()
    {
        $order = $this->createPartialMock(
            Order::class,
            [
                'getQuoteId',
                'getOrderCurrencyCode',
                'getGrandTotal',
                'getIncrementId'
            ]
        );
        $order->expects(self::any())->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $order->expects(self::once())->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $order->expects(self::once())->method('getGrandTotal')->willReturn(self::QUOTE_GRAND_TOTAL);
        $order->expects(self::once())->method('getOrderCurrencyCode')->willReturn(self::ORDER_CURRENCY_CODE);
        $apiHelper = $this->getMockBuilder(ApiHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['buildRequest', 'sendRequest'])
            ->getMock();

        $apiHelper->expects(self::once())->method('buildRequest');
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(true);
        $mockCustomerCreditCard = $this->objectManager->create(CustomerCreditCard::class);
        TestHelper::setProperty($mockCustomerCreditCard, 'apiHelper', $apiHelper);

        $this->assertTrue($mockCustomerCreditCard->recharge($order));
    }

    /**
     * @test
     * @param $data
     * @dataProvider providerGetCardInfoObject
     */
    public function getCardInfoObject($data)
    {
        $this->newCustomerCreditCard->setData('card_info', $data['info']);
        $result = $this->newCustomerCreditCard->getCardInfoObject();
        $cartInfo = $this->dataObjectFactory->create();
        $cartInfo->setData($data['expected']);
        $this->assertEquals($cartInfo, $result);
    }

    public function providerGetCardInfoObject()
    {
        return [
            ['data' =>
                [
                    'info' => self::CARD_INFO,
                    'expected' => [
                        'id' => 'CAfe9tP97CMXs',
                        'last4' => '1111',
                        'display_network' => 'Visa'
                    ]
                ]
            ],
            ['data' =>
                [
                    'info' => '',
                    'expected' => ''
                ]
            ],
            ['data' =>
                [
                    'info' => '{"id":"","last4":"1111","display_network":"Visa"}',
                    'expected' => [
                        'id' => '',
                        'last4' => '1111',
                        'display_network' => 'Visa'
                    ]
                ]
            ]

        ];
    }

    /**
     * @test
     */
    public function getCardType()
    {
        $this->assertEquals('Visa', $this->newCustomerCreditCard->getCardType());
    }

    /**
     * @test
     */
    public function getCardLast4Digit()
    {
        $result = $this->newCustomerCreditCard->getCardLast4Digit();
        $this->assertEquals('XXXX-1111', $result);
    }

    /**
     * @test
     */
    public function getIdentities()
    {
        $result = $this->newCustomerCreditCard->getIdentities();
        $this->assertEquals([CustomerCreditCard::CACHE_TAG . '_' . $this->newCustomerCreditCard->getId()], $result);
    }

    /**
     * @test
     */
    public function saveCreditCard()
    {
        $customerCreditCardId = $this->mockCustomerCreditCard->create()->saveCreditCard(
            $this->customerId,
            self::CONSUMER_ID,
            self::CREDIT_CARD_ID,
            []
        )->getId();
        $this->assertNotNull($customerCreditCardId);
    }
}
