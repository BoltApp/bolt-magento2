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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\CustomFieldsHandler;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\ObjectManager\ObjectManager;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote\Address;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\CustomFieldsHandler
 */
class CustomFieldsHandlerTest extends BoltTestCase
{
    const EMAIL = 'test@bolt.com';
    const USER_ID = 1;

    /**
     * @var CustomFieldsHandler
     */
    private $customFieldsHandler;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    private $customerId;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customFieldsHandler = $this->objectManager->create(CustomFieldsHandler::class);

        $store = Bootstrap::getObjectManager()->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = Bootstrap::getObjectManager()->get(\Magento\Store\Api\WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        $customer = TestUtils::createCustomer(
            $websiteId,
            $storeId,
            [
                "street_address1" => "street",
                "street_address2" => "",
                "locality"        => "Los Angeles",
                "region"          => "California",
                'region_code'     => 'CA',
                'region_id'       => '12',
                "postal_code"     => "11111",
                "country_code"    => "US",
                "country"         => "United States",
                "name"            => "lastname firstname",
                "first_name"      => "firstname",
                "last_name"       => "lastname",
                "phone_number"    => "11111111",
                "email_address"   => self::EMAIL,
            ]
        );
        $this->customerId = $customer->getId();
    }

    /**
     * @test
     */
    public function subscribeToNewsletter_guestUser()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->expects(self::once())->method('getCustomerId')->willReturn(null);

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getEmail')->willReturn(self::EMAIL);
        $orderMock->method('getBillingAddress')->willReturn($addressMock);
        $subscriber = $this->createMock(\Magento\Newsletter\Model\Subscriber::class);
        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($subscriber);
        $subscriber->expects($this->once())->method('subscribe')->with(self::EMAIL);
        TestHelper::setInaccessibleProperty($this->customFieldsHandler,'subscriberFactory', $subscriberFactory);
        $this->customFieldsHandler->subscribeToNewsletter($orderMock);
    }

    /**
     * @test
     */
    public function subscribeToNewsletter_loggedInUser()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getCustomerId')->willReturn(self::USER_ID);
        $subscriber = $this->createMock(\Magento\Newsletter\Model\Subscriber::class);
        $subscriberFactory = $this->createMock(SubscriberFactory::class);
        $subscriberFactory->method('create')->willReturn($subscriber);
        $subscriber->expects($this->once())->method('subscribeCustomerById')->with(self::USER_ID);
        TestHelper::setInaccessibleProperty($this->customFieldsHandler,'subscriberFactory', $subscriberFactory);
        $this->customFieldsHandler->subscribeToNewsletter($orderMock);
    }

    /**
     * @test
     * @dataProvider handleCustomFieldsDataProvider
     *
     * @param $customFields
     * @param $comment
     * @param $needSubscribe
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \ReflectionException
     */
    public function handle($customFields, $comment, $needSubscribe)
    {
        $subscriberFactory = $this->createPartialMock(SubscriberFactory::class,['create','subscribeCustomerById']);
        if ($needSubscribe) {
            $subscriber = $this->createMock(\Magento\Newsletter\Model\Subscriber::class);
            $subscriberFactory->method('create')->willReturn($subscriber);
            $subscriber->expects($this->once())->method('subscribeCustomerById')->with($this->customerId);

        } else {
            $subscriberFactory->expects($this->never())->method('create');
        }
        $order = TestUtils::createDumpyOrder(['customer_id' => $this->customerId]);

        TestHelper::setInaccessibleProperty($this->customFieldsHandler, 'subscriberFactory', $subscriberFactory);

        $this->customFieldsHandler->handle($order, $customFields);
        if ($comment) {
            $commentPrefix = 'BOLTPAY INFO :: customfields';
            self::assertEquals($commentPrefix.$comment, $order->getAllStatusHistory()[0]->getComment());
        }
        TestUtils::cleanupSharedFixtures([$order]);
    }

    public function handleCustomFieldsDataProvider()
    {
        $customField1 = ['label' => 'Gift', 'type' => 'CHECKBOX', 'is_custom_field' => true, 'value' => false];
        $comment1 = '<br>Gift: No';

        $customField2 = ['label' => 'Question', 'type' => 'DROPDOWN', 'is_custom_field' => true, 'value' => 'Answer'];
        $comment2 = '<br>Question: Answer';

        $customField3 = ['label' => 'Subscription', 'type' => 'CHECKBOX', 'is_custom_field' => true, 'value' => true, 'features' => ['subscribe_to_newsletter']];
        $comment3 = '<br>Subscription: Yes';

        return [
            [[], '', false],
            [[$customField1], $comment1, false],
            [[$customField2], $comment2, false],
            [[$customField3], $comment3, true]
        ];
    }
}
