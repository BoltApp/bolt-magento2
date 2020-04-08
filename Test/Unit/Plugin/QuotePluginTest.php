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
            ->setMethods(['getIsActive'])
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
     * @covers ::aroundAfterSave
     * @dataProvider dataProviderAroundAfterSave
     * @param $expectedCall
     * @param $isActive
     */
    public function aroundAfterSave($expectedCall, $isActive)
    {
        $this->callback->expects($expectedCall)->method('__invoke');
        $this->subject->expects(self::any())->method('getIsActive')->willReturn($isActive);
        $this->plugin->aroundAfterSave($this->subject, $this->proceed);

    }

    public function dataProviderAroundAfterSave()
    {
        return [
            [self::once(),true],
            [self::never(),false]
        ];
    }
}
