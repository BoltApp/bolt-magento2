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
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection
     */
    private $mockCustomerCreditCardCollection;

    /**
     * Setup for CollectionTest Class
     */
    public function setUp()
    {
        $this->mockCustomerCreditCardCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init'])
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
}