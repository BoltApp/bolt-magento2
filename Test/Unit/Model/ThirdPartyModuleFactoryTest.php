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

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Module\Manager;

/**
 * Class ThirdPartyModuleFactoryTest
 * @package Bolt\Boltpay\Test\Unit\Model
 * @coversDefaultClass \Bolt\Boltpay\Model\ThirdPartyModuleFactory
 */
class ThirdPartyModuleFactoryTest extends TestCase
{
    const MODULE_NAME = 'Bolt_Boltpay';
    const CLASS_NAME = 'Bolt\Boltpay\Model\ThirdPartyModuleFactory';

    /**
     * @var Manager
     */
    protected $_moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var LogHelper
     */
    private $logHelper;

    private $moduleName;

    private $className;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $currentMock;

    public function setUp()
    {
        $this->_moduleManager = $this->createPartialMock(
            Manager::class,
            ['isEnabled']
        );
        $this->_objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->logHelper = $this->createPartialMock(LogHelper::class, ['addInfoLog']);
        $this->moduleName = self::MODULE_NAME;
        $this->className = self::CLASS_NAME;
        $this->currentMock = $this->getMockBuilder(ThirdPartyModuleFactory::class)
            ->setConstructorArgs([
                $this->_moduleManager,
                $this->_objectManager,
                $this->logHelper,
                $this->moduleName,
                $this->className
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * @covers ::getInstance
     */
    public function getInstance_withModuleIsNotAvailable_returnNull()
    {
        $this->_moduleManager->expects(self::once())
            ->method('isEnabled')->with(self::MODULE_NAME)
            ->willReturn(false);

        $this->logHelper->expects(self::once())
            ->method('addInfoLog')->with('# Module is Disabled or not Found: Bolt_Boltpay')
            ->willReturnSelf();

        $this->assertNull($this->currentMock->getInstance());
    }

    /**
     * @test
     * @covers ::getInstance
     */
    public function getInstance_withModuleIsAvailable_returnObjectManagerObject()
    {
        $this->_moduleManager->expects(self::once())
            ->method('isEnabled')->with(self::MODULE_NAME)
            ->willReturn(true);

        $this->logHelper->expects(self::exactly(2))->method('addInfoLog')
            ->withConsecutive(
                ['# Module is Enabled: Bolt_Boltpay'],
                ['# Class: Bolt\Boltpay\Model\ThirdPartyModuleFactory']
            );

        $this->_objectManager->expects(self::once())->method('create')->willReturnSelf();
        $this->assertSame($this->_objectManager, $this->currentMock->getInstance());
    }

    /**
     * @test
     * @covers ::isAvailable
     */
    public function isAvailable()
    {
        $this->_moduleManager->expects(self::once())
            ->method('isEnabled')->with(self::MODULE_NAME)
            ->willReturn(true);

        $this->assertTrue($this->currentMock->isAvailable());
    }
    
    /**
     * @test
     * @covers ::isExists
     */
    public function isExists()
    {
        $this->assertTrue($this->currentMock->isExists());
    }
}
