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
<<<<<<< HEAD
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
=======
use Klarna\Core\Helper\ConfigHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Framework\DataObjectFactory;
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest

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
     * @var CustomerCreditCard
     */
    private $customerCreditCard;

    /**
<<<<<<< HEAD
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactory;

    /**
=======
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
     * @var ObjectManager
     */
    private $objectManager;

    /**
<<<<<<< HEAD
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    private $customer;

    private $order;
=======
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
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
<<<<<<< HEAD
        $this->customerCreditCard = $this->objectManager->create(CustomerCreditCard::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        $this->customer = TestUtils::createCustomer($websiteId, $storeId, [
=======
        $this->mockCustomerCreditCard = $this->objectManager->create(CustomerCreditCardFactory::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
        $customer = TestUtils::createCustomer($this->websiteId, $this->storeId, [
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
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
<<<<<<< HEAD
            "email_address" => "johntest@bolt.com",
        ]);
        $this->customerCreditCardFactory = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->setCustomerId($this->customer->getId())->setConsumerId(self::CONSUMER_ID)
            ->setCreditCardId(self::CREDIT_CARD_ID)->setCardInfo(self::CARD_INFO)
            ->save();

        $this->dataObjectFactory = $this->objectManager->create(DataObjectFactory::class);
        $this->order = TestUtils::createDumpyOrder();
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures([$this->order]);
        TestUtils::cleanupSharedFixtures([$this->customerCreditCardFactory]);
=======
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
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }

    /**
     * @test
     */
    public function recharge_withException()
    {
<<<<<<< HEAD
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Authentication error. Authorization required.');
        $this->customerCreditCard->recharge($this->order);
=======
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
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }

    /**
     * @test
     */
    public function recharge()
    {
<<<<<<< HEAD
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['buildRequest', 'sendRequest']);
        $apiHelper->expects(self::once())->method('buildRequest')->willReturnSelf();
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(true);

        TestHelper::setProperty($this->customerCreditCard, 'apiHelper', $apiHelper);
        $this->assertTrue($this->customerCreditCard->recharge($this->order));
=======
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
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }

    /**
     * @test
     */
<<<<<<< HEAD
    public function getCardInfoObject()
=======
    public function getCardInfoObject($data)
    {
        $this->newCustomerCreditCard->setData('card_info', $data['info']);
        $result = $this->newCustomerCreditCard->getCardInfoObject();
        $cartInfo = $this->dataObjectFactory->create();
        $cartInfo->setData($data['expected']);
        $this->assertEquals($cartInfo, $result);
    }

    public function providerGetCardInfoObject()
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    {
        $cartInfo = $this->dataObjectFactory->create();
        $cartInfo->setData([
            'id' => 'CAfe9tP97CMXs',
            'last4' => '1111',
            'display_network' => 'Visa'
        ]);

        $this->assertEquals($cartInfo, $this->customerCreditCardFactory->getCardInfoObject());
    }

    /**
     * @test
     */
    public function getCardType()
    {
<<<<<<< HEAD
        $result = $this->customerCreditCardFactory->getCardType();
        $this->assertEquals('Visa', $result);
=======
        $this->assertEquals('Visa', $this->newCustomerCreditCard->getCardType());
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }

    /**
     * @test
     */
    public function getCardLast4Digit()
    {
<<<<<<< HEAD
        $result = $this->customerCreditCardFactory->getCardLast4Digit();
=======
        $result = $this->newCustomerCreditCard->getCardLast4Digit();
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
        $this->assertEquals('XXXX-1111', $result);
    }

    /**
     * @test
     */
    public function getIdentities()
    {
<<<<<<< HEAD
        $result = $this->customerCreditCardFactory->getIdentities();
        $this->assertEquals(['bolt_customer_credit_cards_' . $this->customerCreditCardFactory->getId()], $result);
=======
        $result = $this->newCustomerCreditCard->getIdentities();
        $this->assertEquals([CustomerCreditCard::CACHE_TAG . '_' . $this->newCustomerCreditCard->getId()], $result);
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }

    /**
     * @test
     */
    public function saveCreditCard()
    {
<<<<<<< HEAD
        $customerCreditCard = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->saveCreditCard(
                $this->customer->getId(),
                '1122',
                self::CREDIT_CARD_ID,
                []
            );
        $this->assertNotNull($customerCreditCard->getId());
        TestUtils::cleanupSharedFixtures([$customerCreditCard]);
=======
        $customerCreditCardId = $this->mockCustomerCreditCard->create()->saveCreditCard(
            $this->customerId,
            self::CONSUMER_ID,
            self::CREDIT_CARD_ID,
            []
        )->getId();
        $this->assertNotNull($customerCreditCardId);
>>>>>>> Use integration test instead of unit test for all methods in class Bolt\Boltpay\Test\Unit\Model\CustomerCreditCardTest
    }
}
