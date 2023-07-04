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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Logger\Logger as BoltLogger;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Log;

/**
 * Class LogTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Log
 */
class LogTest extends BoltTestCase
{
    const INFO_MESSAGE = 'info';

    /**
     * @var Log
     */
    private $currentMock;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var BoltLogger
     */
    private $boltLoger;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->context = $this->createMock(Context::class);
        $this->boltLoger = $this->createPartialMock(
            BoltLogger::class,
            ['info']
        );
        $this->configHelper = $this->createPartialMock(
            ConfigHelper::class,
            ['isDebugModeOn']
        );


        $this->currentMock = $this->getMockBuilder(Log::class)
            ->setMethods(['info', 'isDebugModeOn'])
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->boltLoger,
                    $this->configHelper
                ]
            )
            ->getMock();
    }

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Log(
            $this->context,
            $this->boltLoger,
            $this->configHelper
        );
        
        static::assertAttributeEquals($this->boltLoger, 'boltLogger', $instance);
        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
    }

    /**
     * @test
     */
    public function addInfoLog_success()
    {
        $this->configHelper->expects(self::once())->method('isDebugModeOn')->willReturn(true);
        $this->boltLoger->expects(self::once())->method('info')->with(self::INFO_MESSAGE)->willReturnSelf();

        $this->assertSame($this->currentMock, $this->currentMock->addInfoLog(self::INFO_MESSAGE));
    }

    /**
     * @test
     */
    public function addInfoLog_fail()
    {
        $this->configHelper->expects(self::once())->method('isDebugModeOn')->willReturn(false);
        $this->boltLoger->expects(self::never())->method('info');

        $this->assertSame($this->currentMock, $this->currentMock->addInfoLog(self::INFO_MESSAGE));
    }
}
