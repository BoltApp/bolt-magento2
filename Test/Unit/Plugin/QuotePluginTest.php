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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\QuotePlugin;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class QuotePluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\QuotePlugin
 */
class QuotePluginTest extends TestCase
{
    /**
     * @var Quote
     */
    protected $subject;

    /**
     * @var QuotePlugin
     */
    protected $plugin;

    /**
     * @var callable
     */
    protected $proceed;

    /** @var callable|MockObject */
    protected $callback;

    public function setUp()
    {
        $this->subject = $this->getMockBuilder(Quote::class)
            ->setMethods(['getBoltParentQuoteId', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();

        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function () use ($callback) {
            return $callback();
        };

        $this->plugin = (new ObjectManager($this))->getObject(
            QuotePlugin::class
        );
    }

    /**
     * @test
     * that aroundAfterSave should return proceed if subject have boltParentQuoteId
     * and boltParentQuoteId is not same as getId
     *
     * @covers ::aroundAfterSave
     * @dataProvider dataProviderAroundAfterSave
     * @param $boltParentQuoteId
     * @param $id
     * @param $numCallsParentId
     * @param $numCallsId
     * @param $expectedCall
     */
    public function aroundAfterSave($boltParentQuoteId, $id, $numCallsParentId, $numCallsId, $expectedCall)
    {
        $this->subject
            ->expects(self::exactly($numCallsParentId))
            ->method('getBoltParentQuoteId')
            ->willReturn($boltParentQuoteId);
        $this->subject
            ->expects(self::exactly($numCallsId))
            ->method('getId')
            ->willReturn($id);
        $this->callback->expects($expectedCall)->method('__invoke');
        $this->plugin->aroundAfterSave($this->subject, $this->proceed);
    }

    public function dataProviderAroundAfterSave()
    {
        return [
            [1111, 1111, 2, 1, self::once()],
            [1111, 2222, 2, 1, self::never()],
            [0, 2222, 1, 0, self::once()],
            [null, null, 1, 0, self::once()]
        ];
    }
}
