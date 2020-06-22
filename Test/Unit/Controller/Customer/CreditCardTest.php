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

namespace Bolt\Boltpay\Test\Unit\Controller\Customer;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Controller\Customer\CreditCard;
use Magento\Framework\App\ViewInterface;

/**
 * Class CreditCardTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Customer
 * @coversDefaultClass \Bolt\Boltpay\Controller\Customer\CreditCard
 */
class CreditCardTest extends TestCase
{
    /**
     * @var Context
     */
    protected $_context;

    /**
     * @var CreditCard
     */
    protected $_mockTest;

    /**
     * @var ViewInterface
     */
    protected $_view;

    protected function setUp()
    {
        $this->_context = $this->createPartialMock(Context::class, ['getView']);
        $this->_view = $this->createPartialMock(
            ViewInterface::class,
            [
                'loadLayout', 'renderLayout', 'loadLayoutUpdates',
                'getDefaultLayoutHandle', 'generateLayoutXml', 'addPageLayoutHandles',
                'generateLayoutBlocks', 'getPage', 'getLayout', 'addActionLayoutHandles',
                'setIsLayoutLoaded', 'isLayoutLoaded'
            ]
        );
        $this->_context->method('getView')->willReturn($this->_view);
        $this->_mockTest = $this->getMockBuilder(CreditCard::class)
            ->setConstructorArgs([
                $this->_context
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute()
    {
        $this->_view->expects(self::once())->method('loadLayout')->willReturnSelf();
        $this->_view->expects(self::once())->method('renderLayout')->willReturnSelf();
        $this->_mockTest->execute();
    }
}
