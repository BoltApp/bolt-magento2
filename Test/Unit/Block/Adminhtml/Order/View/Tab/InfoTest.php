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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Order\View\Tab;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class InfoTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Bolt\Boltpay\Block\Adminhtml\Order\View\Tab\Info
     */
    protected $block;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $this->block = $objectManager->getObject(
            \Bolt\Boltpay\Block\Adminhtml\Order\View\Tab\Info::class
        );

        $this->order = $this->createPartialMock(\Magento\Sales\Model\Order::class, ['getPayment','getMethod']);
    }

    /**
     * @test
     */
    public function isBoltOrder_willReturnTrue()
    {
        $this->order->method('getPayment')->willReturnSelf();
        $this->order->method('getMethod')->willReturn(\Bolt\Boltpay\Model\Payment::METHOD_CODE);
        $this->assertTrue($this->block->isBoltOrder($this->order));
    }

    /**
     * @test
     */
    public function isBoltOrder_withoutPayment_willReturnFalse()
    {
        $this->order->method('getPayment')->willReturn(null);
        $this->assertFalse($this->block->isBoltOrder($this->order));
    }

    /**
     * @test
     */
    public function isBoltOrder_withPaymentMethodIsNotBoltPay_willReturnFalse()
    {
        $this->order->method('getPayment')->willReturnSelf();
        $this->order->method('getMethod')->willReturn('is_not_BoltPay');
        $this->assertFalse($this->block->isBoltOrder($this->order));
    }
}
