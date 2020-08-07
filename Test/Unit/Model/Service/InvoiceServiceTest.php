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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use PHPUnit\Framework\TestCase;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\InvoiceCommentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Sales\Model\Order\InvoiceNotifier;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order as OrderModel;
use Bolt\Boltpay\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Item;

/**
 * Class InvoiceServiceTest
 * @package Bolt\Boltpay\Test\Unit\Model
 * @coversDefaultClass \Bolt\Boltpay\Model\Service\InvoiceService
 */
class InvoiceServiceTest extends TestCase
{
    const AMOUNT = 20;

    /**
     * Repository
     *
     * @var InvoiceRepositoryInterface
     */
    protected $repository;

    /**
     * Repository
     *
     * @var InvoiceCommentRepositoryInterface
     */
    protected $commentRepository;

    /**
     * Search Criteria Builder
     *
     * @var SearchCriteriaBuilder
     */
    protected $criteriaBuilder;

    /**
     * Filter Builder
     *
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * Invoice Notifier
     *
     * @var InvoiceNotifier
     */
    protected $invoiceNotifier;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Order
     */
    protected $orderConverter;

    /**
     * @var OrderInterface
     */
    protected $order;

    /**
     * @var Item
     */
    protected $orderItem;

    /**
     * @var InvoiceService
     */
    private $currentMock;

    public function setUp()
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->commentRepository = $this->createMock(InvoiceCommentRepositoryInterface::class);
        $this->criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->invoiceNotifier = $this->createMock(InvoiceNotifier::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderConverter = $this->createPartialMock(
            Order::class,
            [
                'toInvoice',
                'setBaseGrandTotal',
                'setSubtotal',
                'setBaseSubtotal',
                'setGrandTotal'
            ]
        );
        $this->order = $this->createPartialMock(
            OrderModel::class,
            ['getInvoiceCollection']
        );


        $this->orderItem = $this->createPartialMock(
            Item::class,
            ['addItem']
        );

        $this->serializer = $this->createMock(JsonSerializer::class);

        $this->currentMock = $this->getMockBuilder(InvoiceService::class)
            ->setConstructorArgs([
                $this->repository,
                $this->commentRepository,
                $this->criteriaBuilder,
                $this->filterBuilder,
                $this->invoiceNotifier,
                $this->orderRepository,
                $this->orderConverter,
                $this->serializer
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @covers ::prepareInvoiceWithoutItems
     * @test
     */
    public function prepareInvoiceWithoutItems()
    {
        $this->orderConverter->expects(self::once())->method('toInvoice')->with($this->order)->willReturnSelf();
        $this->orderConverter->expects(self::once())->method('setBaseGrandTotal')->with(self::AMOUNT)->willReturnSelf();
        $this->orderConverter->expects(self::once())->method('setSubtotal')->with(self::AMOUNT)->willReturnSelf();
        $this->orderConverter->expects(self::once())->method('setBaseSubtotal')->with(self::AMOUNT)->willReturnSelf();
        $this->orderConverter->expects(self::once())->method('setGrandTotal')->with(self::AMOUNT)->willReturnSelf();
        $this->order->expects(self::once())->method('getInvoiceCollection')->willReturn($this->orderItem);
        $this->orderItem->expects(self::once())->method('addItem')->willReturn($this->repository);

        $this->currentMock->prepareInvoiceWithoutItems($this->order, self::AMOUNT);
    }
}
