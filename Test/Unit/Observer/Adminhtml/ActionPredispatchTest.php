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

namespace Bolt\Boltpay\Test\Unit\Observer\Adminhtml;

use Bolt\Boltpay\Observer\Adminhtml\ActionPredispatch;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Observer\Adminhtml\ActionPredispatch
 */
class ActionPredispatchTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\EventsForThirdPartyModules|\PHPUnit\Framework\MockObject\MockObject
     */
    private $eventsForThirdPartyModulesMock;

    /**
     * @var ActionPredispatch|\PHPUnit\Framework\MockObject\MockObject
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->eventsForThirdPartyModulesMock = $this->createMock(
            \Bolt\Boltpay\Model\EventsForThirdPartyModules::class
        );
        $this->currentMock = $this->getMockBuilder(ActionPredispatch::class)
            ->setMethods(null)
            ->setConstructorArgs([$this->eventsForThirdPartyModulesMock])
            ->getMock();
    }

    /**
     * @test
     * that constructor sets provided arguments to expected properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperty()
    {
        $instance = new ActionPredispatch($this->eventsForThirdPartyModulesMock);
        static::assertAttributeEquals($this->eventsForThirdPartyModulesMock, 'eventsForThirdPartyModules', $instance);
    }

    /**
     * @test
     * that excute redirects the predispatch event to the Events for Third Party Modules
     *
     * @dataProvider executeDataProvider
     *
     * @covers ::execute
     * @covers ::convertEventName
     *
     * @param string $route
     * @param string $controller
     * @param string $action
     * @param array  $expectedThirdPartyEvents
     */
    public function execute_withVariousActions_dispatchesThirdPartyEvents(
        $route,
        $controller,
        $action,
        $expectedThirdPartyEvents
    ) {
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $request->expects(static::once())->method('getRouteName')->willReturn($route);
        $request->expects(static::once())->method('getFullActionName')->willReturn(
            implode('_', [$route, $controller, $action])
        );
        $observer = new \Magento\Framework\Event\Observer(['request' => $request]);
        $this->eventsForThirdPartyModulesMock->expects(static::exactly(count($expectedThirdPartyEvents)))
            ->method('dispatchEvent')
            ->withConsecutive(
                ...array_chunk($expectedThirdPartyEvents, 1)
            );
        $this->currentMock->execute($observer);
    }

    /**
     * Data provider for {@see execute_withVariousAction}
     */
    public function execute_withVariousActionProvider()
    {
        return [
            [
                'route'                    => 'sales',
                'controller'               => 'order_create',
                'action'                   => 'index',
                'expectedThirdPartyEvents' => [
                    'adminhtmlControllerActionPredispatch',
                    'adminhtmlControllerActionPredispatchSales',
                    'adminhtmlControllerActionPredispatchSalesOrderCreateIndex',
                ]
            ],
            [
                'route'                    => 'sales',
                'controller'               => 'order_create',
                'action'                   => 'start',
                'expectedThirdPartyEvents' => [
                    'adminhtmlControllerActionPredispatch',
                    'adminhtmlControllerActionPredispatchSales',
                    'adminhtmlControllerActionPredispatchSalesOrderCreateStart',
                ]
            ],
            [
                'route'                    => 'catalog',
                'controller'               => 'product',
                'action'                   => 'edit',
                'expectedThirdPartyEvents' => [
                    'adminhtmlControllerActionPredispatch',
                    'adminhtmlControllerActionPredispatchCatalog',
                    'adminhtmlControllerActionPredispatchCatalogProductEdit',
                ]
            ],
        ];
    }

}
