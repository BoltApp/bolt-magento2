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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\CustomerCreditCard;

use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection;
use Bolt\Boltpay\Model\CustomerCreditCard;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    const ID = '1110';
    const CUSTOMER_ID = '1111';
    const CONSUMER_ID = '1112';
    const CREDIT_CARD_ID = '1113';
    const CARD_INFO = '{"last4":"1111","display_network":"Visa"}';

    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection
     */
    private $mockCustomerCreditCardCollection;

    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCard
     */
    private $mockCustomerCreditCard;

    /**
     * Setup for CollectionTest Class
     */
    public function setUp()
    {
        $this->mockCustomerCreditCardCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init','addFilter'])
            ->getMock();
        $this->mockCustomerCreditCard = $this->getMockBuilder(CustomerCreditCard::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCardInfo','getCustomerId','getConsumerId','getCreditCardId','getId'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->mockCustomerCreditCardCollection->expects($this->once())->method('_init')
            ->with('Bolt\Boltpay\Model\CustomerCreditCard', 'Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard')
            ->willReturnSelf();

        $testMethod = new \ReflectionMethod(Collection::class, '_construct');
        $testMethod->setAccessible(true);
        $testMethod->invokeArgs($this->mockCustomerCreditCardCollection, []);
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard'));
        $this->assertTrue(class_exists('Bolt\Boltpay\Model\CustomerCreditCard'));
    }

    /**
     * @test
     */
    public function getCreditCardInfosByCustomerId_withValidData(){
        $this->mockCustomerCreditCard->expects(self::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->mockCustomerCreditCard->expects(self::once())->method('getConsumerId')->willReturn(self::CONSUMER_ID);
        $this->mockCustomerCreditCard->expects(self::once())->method('getCreditCardId')->willReturn(self::CREDIT_CARD_ID);
        $this->mockCustomerCreditCard->expects(self::once())->method('getCardInfo')->willReturn(self::CARD_INFO);
        $this->mockCustomerCreditCard->expects(self::once())->method('getId')->willReturn(self::ID);

        $this->mockCustomerCreditCardCollection->expects($this->once())->method('addFilter')
            ->with('customer_id', self::CUSTOMER_ID)
            ->willReturn([$this->mockCustomerCreditCard]);

        $expected[] = [
            'card_info'=>[
                'display_network'=> 'Visa',
                'last4' => '1111'
            ],
            'consumer_id'=> self::CONSUMER_ID,
            'credit_card_id'=> self::CREDIT_CARD_ID,
            'customer_id'=> self::CUSTOMER_ID,
            'id'=> self::ID
        ];

        $result = $this->mockCustomerCreditCardCollection->getCreditCardInfosByCustomerId(self::CUSTOMER_ID);
        $this->assertEquals($expected,$result);
    }

    /**
     * @test
     */
    public function getCreditCardInfosByCustomerId_withNonExistedCustomerId(){
        $this->mockCustomerCreditCardCollection
            ->expects(self::once())
            ->method('addFilter')
            ->with('customer_id', self::CUSTOMER_ID)
            ->willReturn([]);

        $result = $this->mockCustomerCreditCardCollection->getCreditCardInfosByCustomerId(self::CUSTOMER_ID);
        $this->assertEquals([],$result);
    }

    /**
     * @test
     */
    public function getCreditCardInfosByCustomerId_withInvalidCardInfo(){
        $this->mockCustomerCreditCard->expects(self::once())->method('getCardInfo')->willReturn(null);

        $this->mockCustomerCreditCard->expects(self::never())->method('getCustomerId');
        $this->mockCustomerCreditCard->expects(self::never())->method('getConsumerId');
        $this->mockCustomerCreditCard->expects(self::never())->method('getCreditCardId');
        $this->mockCustomerCreditCard->expects(self::never())->method('getId');

        $this->mockCustomerCreditCardCollection
            ->expects(self::once())
            ->method('addFilter')
            ->with('customer_id', self::CUSTOMER_ID)
            ->willReturn([$this->mockCustomerCreditCard]);

        $result = $this->mockCustomerCreditCardCollection->getCreditCardInfosByCustomerId(self::CUSTOMER_ID);
        $this->assertEquals([],$result);
    }
}