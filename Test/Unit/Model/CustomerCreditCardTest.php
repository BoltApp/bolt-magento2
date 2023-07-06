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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Model\CustomerCreditCard;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    private $customer;

    private $order;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->configHelper = $this->objectManager->get(ConfigHelper::class);
        $this->customerCreditCard = $this->objectManager->create(CustomerCreditCard::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        $this->customer = TestUtils::createCustomer($websiteId, $storeId, [
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
    }

    /**
     * @test
     */
    public function recharge_withException()
    {
        $exceptionMsg = "Authentication error. Authorization required.";
        // in magento version under 2.3.0 zend framework contains a bug in header response regexp parser in class Zend_Http_Response::extractHeaders
        // which is throwing the "Invalid header line detected" exception if response has: "HTTP/2 401" header line.
        if (version_compare($this->configHelper->getStoreVersion(), '2.3.0', '<')) {
            $exceptionMsg = "Invalid header line detected";
        }
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($exceptionMsg);
        $this->customerCreditCard->recharge($this->order);
    }

    /**
     * @test
     */
    public function recharge()
    {
        $apiHelper = $this->createPartialMock(ApiHelper::class, ['buildRequest', 'sendRequest']);
        $apiHelper->expects(self::once())->method('buildRequest')->willReturnSelf();
        $apiHelper->expects(self::once())->method('sendRequest')->willReturn(true);

        TestHelper::setProperty($this->customerCreditCard, 'apiHelper', $apiHelper);
        $this->assertTrue($this->customerCreditCard->recharge($this->order));
    }

    /**
     * @test
     */
    public function getCardInfoObject()
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
        $result = $this->customerCreditCardFactory->getCardType();
        $this->assertEquals('Visa', $result);
    }

    /**
     * @test
     */
    public function getCardLast4Digit()
    {
        $result = $this->customerCreditCardFactory->getCardLast4Digit();
        $this->assertEquals('XXXX-1111', $result);
    }

    /**
     * @test
     */
    public function getIdentities()
    {
        $result = $this->customerCreditCardFactory->getIdentities();
        $this->assertEquals(['bolt_customer_credit_cards_' . $this->customerCreditCardFactory->getId()], $result);
    }

    /**
     * @test
     */
    public function saveCreditCard()
    {
        $customerCreditCard = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->saveCreditCard(
                $this->customer->getId(),
                '1122',
                self::CREDIT_CARD_ID,
                []
            );
        $this->assertNotNull($customerCreditCard->getId());
        TestUtils::cleanupSharedFixtures([$customerCreditCard]);
    }
}
