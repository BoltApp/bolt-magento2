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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\CustomFieldsHandler;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Newsletter\Model\SubscriberFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote\Address;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\CustomFieldsHandler
 */
class CustomFieldsHandlerTest extends BoltTestCase
{

    const EMAIL = 'test@bolt.com';
    const USER_ID = 1;
    /**
     * @var Context
     */
    private $context;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    private $orderMock;
    private $subscriber;


    public function setUpInternal()
    {
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subscriberFactory = $this->createMock(SubscriberFactory::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->initCurrentMock([]);
    }

    /**
     * @param array $methods
     * @param bool  $enableOriginalConstructor
     * @param bool  $enableProxyingToOriginalMethods
     */
    private function initCurrentMock(
        $methods = [],
        $enableOriginalConstructor = true,
        $enableProxyingToOriginalMethods = false
    ) {
        $builder = $this->getMockBuilder(CustomFieldsHandler::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->bugsnag,
                    $this->subscriberFactory
                ]
            )
            ->setMethods($methods);

        if ($enableOriginalConstructor) {
            $builder->enableOriginalConstructor();
        } else {
            $builder->disableOriginalConstructor();
        }

        if ($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        } else {
            $builder->disableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
    }

    private function subscribeToNewsletter_setup()
    {
        $this->initCurrentMock([], true, true);
        $this->orderMock = $this->createMock(Order::class);
        $this->subscriber = $this->createMock(\Magento\Newsletter\Model\Subscriber::class);
        $this->subscriberFactory->method('create')->willReturn($this->subscriber);
    }

    /**
     * @test
     * @covers ::subscribeToNewsletter
     */
    public function subscribeToNewsletter_guestUser()
    {
        $this->subscribeToNewsletter_setup();
        $this->orderMock->expects(self::once())->method('getCustomerId')->willReturn(null);

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getEmail')->willReturn(self::EMAIL);
        $this->orderMock->method('getBillingAddress')->willReturn($addressMock);

        $this->subscriber->expects($this->once())->method('subscribe')->with(self::EMAIL);

        $this->currentMock->subscribeToNewsletter($this->orderMock);
    }

    /**
     * @test
     *
     * @covers ::subscribeToNewsletter
     */
    public function subscribeToNewsletter_loggedInUser()
    {
        $this->subscribeToNewsletter_setup();
        $this->orderMock->method('getCustomerId')->willReturn(self::USER_ID);

        $this->subscriber->expects($this->once())->method('subscribeCustomerById')->with(self::USER_ID);

        $this->currentMock->subscribeToNewsletter($this->orderMock);
    }


    /**
     * @test
     * @dataProvider handleCustomFieldsDataProvider
     * @covers ::handle
     */
    public function handle($customFields, $comment, $needSubscribe)
    {
        $this->initCurrentMock(['subscribeToNewsletter']);
        $this->orderMock = $this->createPartialMock(
            Order::class,
            [
                'save',
                'addCommentToStatusHistory'
            ]
        );

        if ($comment) {
            $commentPrefix = 'BOLTPAY INFO :: customfields';
            $this->orderMock->expects($this->once())->method('addCommentToStatusHistory')
                ->with($commentPrefix.$comment);
            $this->orderMock->expects($this->once())->method('save');
        } else {
            $this->orderMock->expects($this->never())->method('addCommentToStatusHistory');
            $this->orderMock->expects($this->never())->method('save');
        }
        if ($needSubscribe) {
            $this->currentMock->expects($this->once())->method('subscribeToNewsletter')
                ->with($this->orderMock);
        } else {
            $this->currentMock->expects($this->never())->method('subscribeToNewsletter');
        }
        $this->currentMock->handle($this->orderMock, $customFields);
    }

    public function handleCustomFieldsDataProvider()
    {
        $customField1 = ['label' => 'Gift', 'type'=>'CHECKBOX', 'is_custom_field' => true, 'value' => false];
        $comment1 = '<br>Gift: No';
      
        $customField2 = ['label' => 'Question', 'type' => 'DROPDOWN', 'is_custom_field' => true, 'value' => 'Answer'];
        $comment2 = '<br>Question: Answer';
        return [
            [[], '', false],
            [[$customField1], $comment1, false],
            [[$customField2], $comment2, false]           
        ];
    }
}
