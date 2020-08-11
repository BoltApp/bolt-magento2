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

namespace Bolt\Boltpay\Test\Unit\Model\System\Message;

use Bolt\Boltpay\Model\System\Message\BoltUpdateAvailable;
use Bolt\Boltpay\Model\Updater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\System\Message\BoltUpdateAvailable
 */
class BoltUpdateAvailableTest extends TestCase
{
    /**
     * @var Updater|MockObject mocked instance of the Updater
     */
    private $updater;

    /**
     * @var BoltUpdateAvailable|MockObject mocked instance of the class tested
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    protected function setUp()
    {
        $this->updater = $this->createPartialMock(
            Updater::class,
            [
                'getIsUpdateAvailable',
                'getUpdateTitle',
                'getUpdateSeverity',
            ]
        );
        $this->currentMock = $this->getMockBuilder(BoltUpdateAvailable::class)
            ->setConstructorArgs(
                [
                    $this->updater,
                ]
            )
            ->setMethods()
            ->getMock();
    }

    /**
     * @test
     * that constructor sets properties to provided values
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new BoltUpdateAvailable(
            $this->updater
        );

        static::assertAttributeEquals($this->updater, 'updater', $instance);
    }


    /**
     * @test
     * that getIdentity returns BOLT_UPDATE_AVAILABLE for message identity
     *
     * @covers ::getIdentity
     */
    public function getIdentity_always_returnsMessageIdentity()
    {
        $this->assertEquals('BOLT_UPDATE_AVAILABLE', $this->currentMock->getIdentity());
    }

    /**
     * @test
     * that isDisplayed returns bool value according to update availability
     *
     * @covers ::isDisplayed
     *
     * @dataProvider isDisplayed_withVariousUpdateAvailabilityProvider
     *
     * @param bool $updateAvailable flag
     * @param bool $expectedResult of the tested method
     */
    public function isDisplayed_withVariousUpdateAvailability_returnsDisplayedBool($updateAvailable, $expectedResult)
    {
        $this->updater->expects(static::once())->method('getIsUpdateAvailable')->willReturn($updateAvailable);
        $this->assertEquals($expectedResult, $this->currentMock->isDisplayed());
    }

    /**
     * Data provider for {@see isDisplayed_withVariousUpdateAvailability_returnsDisplayedBool}
     *
     * @return array[] containing update available flag and expected result of the tested method call
     */
    public function isDisplayed_withVariousUpdateAvailabilityProvider()
    {
        return [
            ['updateAvailable' => true, 'expectedResult' => true],
            ['updateAvailable' => false, 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that getText returns update title from {@see \Bolt\Boltpay\Model\Updater::getUpdateTitle}
     *
     * @covers ::getText
     */
    public function getText_always_returnsUpdateTitle()
    {
        $updateTitle = 'Update Available';
        $this->updater->expects(static::once())->method('getUpdateTitle')->willReturn($updateTitle);
        $this->assertEquals($updateTitle, $this->currentMock->getText());
    }

    /**
     * @test
     * that getSeverity returns severity message
     *
     * @covers ::getSeverity
     */
    public function getSeverity_always_returnsMessage()
    {
        $severityMessage = \Magento\Framework\Notification\MessageInterface::SEVERITY_CRITICAL;
        $this->updater->expects(static::once())->method('getUpdateSeverity')->willReturn($severityMessage);
        $this->assertEquals($severityMessage, $this->currentMock->getSeverity());
    }
}
