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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\CheckboxesHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Newsletter\Model\SubscriberFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote\Address;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\CheckboxesHandler
 */
class CheckboxesHandlerTest extends TestCase
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


    public function setUp()
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
        $builder = $this->getMockBuilder(CheckboxesHandler::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->bugsnag,
                    $this->subscriberFactory
                ]
            )
            ->setMethods($methods);

        if($enableOriginalConstructor) {
            $builder->enableOriginalConstructor();
        } else {
            $builder->disableOriginalConstructor();
        }

        if($enableProxyingToOriginalMethods) {
            $builder->enableProxyingToOriginalMethods();
        } else {
            $builder->disableProxyingToOriginalMethods();
        }

        $this->currentMock = $builder->getMock();
    }

    private function subscribeForNewsletter_setup()
    {
        $this->initCurrentMock([],true,true);
        $this->orderMock = $this->createMock(Order::class);
        $this->subscriber = $this->createMock(\Magento\Newsletter\Model\Subscriber::class);
        $this->subscriberFactory->method('create')->willReturn($this->subscriber);
    }

    /**
     * @test
     * @covers ::subscribeToNewsletter
     */
    public function subscribeForNewsletter_guestUser()
    {
        $this->subscribeForNewsletter_setup();
        $this->orderMock->expects(self::once())->method('getCustomerId')->willReturn(null);

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getEmail')->willReturn(SELF::EMAIL);
        $this->orderMock->method('getBillingAddress')->willReturn($addressMock);

        $this->subscriber->expects($this->once())->method('subscribe')->with(SELF::EMAIL);

        $this->currentMock->subscribeForNewsletter($this->orderMock);
    }

    /**
     * @test
     *
     * @covers ::subscribeToNewsletter
     */
    public function subscribeForNewsletter_loggedInUser()
    {
        $this->subscribeForNewsletter_setup();
        $this->orderMock->method('getCustomerId')->willReturn(self::USER_ID);

        $this->subscriber->expects($this->once())->method('subscribeCustomerById')->with(self::USER_ID);

        $this->currentMock->subscribeForNewsletter($this->orderMock);
    }


    /**
     * @test
     * @dataProvider handleCheckboxesDataProvider
     * @covers ::handle
     */
    public function handle($checkboxes, $comment, $needSubscribe) {
        $this->initCurrentMock(['subscribeForNewsletter']);
        $this->orderMock = $this->createPartialMock(
            Order::class,
            [
                'save',
                'addCommentToStatusHistory'
            ]
        );

        if ($comment) {
            $commentPrefix = 'BOLTPAY INFO :: checkboxes';
            $this->orderMock->expects($this->once())->method('addCommentToStatusHistory')
                ->with($commentPrefix.$comment);
            $this->orderMock->expects($this->once())->method('save');
        } else {
            $this->orderMock->expects($this->never())->method('addCommentToStatusHistory');
            $this->orderMock->expects($this->never())->method('save');
        }
        if ($needSubscribe) {
            $this->currentMock->expects($this->once())->method('subscribeForNewsletter')
                ->with($this->orderMock);
        } else {
            $this->currentMock->expects($this->never())->method('subscribeForNewsletter');
        }
        $this->currentMock->handle($this->orderMock,$checkboxes);
    }

    public function handleCheckboxesDataProvider() {
        $checkbox1 = ['text'=>'Subscribe for our newsletter','category'=>'NEWSLETTER','value'=>true];
        $comment1 = '<br>Subscribe for our newsletter: Yes';
        $checkbox2 = ['text'=>'Gift','category'=>'OTHER','value'=>false];
        $comment2 = '<br>Gift: No';
        return [
            [[], '', false],
            [[$checkbox1], $comment1, true],
            [[$checkbox2], $comment2, false],
            [[$checkbox1,$checkbox2], $comment1.$comment2, true],
        ];
    }


}
