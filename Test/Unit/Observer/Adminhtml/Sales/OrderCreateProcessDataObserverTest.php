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

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml\Sales;

use Magento\Framework\Event;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Event\Observer;
use Bolt\Boltpay\Observer\Adminhtml\Sales\OrderCreateProcessDataObserver;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class OrderCreateProcessDataObserverTest
 * @coversDefaultClass \Bolt\Boltpay\Model\Payment
 */
class OrderCreateProcessDataObserverTest extends TestCase
{
    /**
     * @var OrderCreateProcessDataObserver
     */
    protected $orderCreateProcessDataObserverTest;

    /**
     * @var Observer
     */
    protected $observer;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var Event
     */
    protected $event;

    /**
     * @var Quote
     */
    protected $quote;

    protected function setUp()
    {
        $this->observer = $this->createPartialMock(
            Observer::class,
            ['getEvent', 'getData', 'getQuote']
        );
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);

        $this->quote = $this->createPartialMock(Quote::class, ['setCustomerEmail']);
        $this->event = $this->createPartialMock(Event::class, ['getData', 'getQuote']);
        $this->orderCreateProcessDataObserverTest = $this->getMockBuilder(OrderCreateProcessDataObserver::class)
            ->setConstructorArgs([
                $this->productMetadata
            ])
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function execute_withVersionIsGreaterThan214()
    {
        $this->productMetadata->expects(self::once())->method('getVersion')->willReturn('2.1.5');
        $this->observer->expects(self::never())->method('getEvent')->willReturnSelf();
        $this->orderCreateProcessDataObserverTest->execute($this->observer);
    }

    /**
     * @test
     */
    public function execute_withVersionIsLessThan214()
    {
        $this->event->expects(self::exactly(2))->method('getData')
            ->withConsecutive(['order_create_model'], ['account'])
            ->willReturnOnConsecutiveCalls($this->event, ['email' => 'email@gmail.com']);
        $this->event->expects(self::once())->method('getQuote')->willReturn($this->quote);
        $this->quote->expects(self::once())->method('setCustomerEmail')->with('email@gmail.com')->willReturn($this->quote);
        $this->productMetadata->expects(self::once())->method('getVersion')->willReturn('2.1.3');
        $this->observer->expects(self::once())->method('getEvent')->willReturn($this->event);

        $this->orderCreateProcessDataObserverTest->execute($this->observer);
    }
}
