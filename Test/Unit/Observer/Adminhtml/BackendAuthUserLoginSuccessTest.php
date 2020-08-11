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

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\Updater;
use Bolt\Boltpay\Observer\Adminhtml\BackendAuthUserLoginSuccess;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\AdminNotification\Model\Inbox;
use Magento\AdminNotification\Model\ResourceModel\Inbox\Collection as AdminNotificationInboxCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Notification\MessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Module\Manager;
use Magento\AdminNotification\Model\ResourceModel\Inbox\CollectionFactory;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Observer\Adminhtml\BackendAuthUserLoginSuccess
 */
class BackendAuthUserLoginSuccessTest extends TestCase
{
    /**
     * @var Updater|MockObject mocked instance of the Bolt updater class
     */
    private $updater;

    /**
     * @var Manager|MockObject mocked instance of the module manager class
     */
    private $moduleManager;

    /**
     * @var Config|MockObject mocked instance of the config helper class
     */
    private $config;

    /**
     * @var CollectionFactory|MockObject mocked instance of the admin notification collection factory class
     */
    private $adminNotificationCollectionFactory;

    /**
     * @var Inbox|MockObject mocked instance of the admin notification inbox class
     */
    private $adminNotificationInbox;

    /**
     * @var Observer|MockObject mocked instance of the observer class, provided to
     * {@see \Bolt\Boltpay\Observer\Adminhtml\BackendAuthUserLoginSuccess::execute}
     */
    private $observer;

    /**
     * @var BackendAuthUserLoginSuccess|MockObject mocked instance of the class tested
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->updater = $this->getMockBuilder(Updater::class)
            ->setMethods(
                [
                    'getVersion',
                    'getSeverity',
                    'getIsUpdateAvailable',
                    'getUpdateTitle',
                    'getUpdateSeverity',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->moduleManager = $this->createMock(Manager::class);
        $this->config = $this->createMock(Config::class);
        $this->adminNotificationInbox = $this->createMock(Inbox::class);
        $this->adminNotificationCollectionFactory = $this->createMock(CollectionFactory::class);

        $this->observer = $this->createMock(Observer::class);
        $this->currentMock = $this->getMockBuilder(BackendAuthUserLoginSuccess::class)
            ->setConstructorArgs(
                [
                    $this->updater,
                    $this->moduleManager,
                    $this->config,
                    $this->adminNotificationInbox,
                    $this->adminNotificationCollectionFactory
                ]
            )
            ->setMethods(null)
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
        $instance = new BackendAuthUserLoginSuccess(
            $this->updater,
            $this->moduleManager,
            $this->config,
            $this->adminNotificationInbox,
            $this->adminNotificationCollectionFactory
        );

        static::assertAttributeEquals($this->updater, 'updater', $instance);
        static::assertAttributeEquals($this->moduleManager, 'moduleManager', $instance);
        static::assertAttributeEquals($this->config, 'config', $instance);
        static::assertAttributeEquals($this->adminNotificationInbox, 'adminNotificationInbox', $instance);
        static::assertAttributeEquals(
            $this->adminNotificationCollectionFactory,
            'adminNotificationCollectionFactory',
            $instance
        );
    }

    /**
     * @test
     * that execute stops executing when the Module Manager is disabled or update is unavailable
     *
     * @covers ::execute
     *
     * @dataProvider execute_withVariousAvailabilityProvider
     *
     * @param bool $moduleManagerAvailable flag
     * @param bool $updateAvailable flag
     * @param null $expectedResult of the tested method
     */
    public function execute_withVariousAvailability_stopsExecuting(
        $moduleManagerAvailable,
        $updateAvailable,
        $expectedResult
    ) {
        $this->moduleManager->expects(static::once())->method('isEnabled')
            ->with('Magento_AdminNotification')
            ->willReturn($moduleManagerAvailable);

        $this->updater->expects($moduleManagerAvailable ? static::once() : static::never())
            ->method('getIsUpdateAvailable')->willReturn($updateAvailable);

        static::assertEquals($expectedResult, $this->currentMock->execute($this->observer));
    }

    /**
     * Data provider for {@see execute_withVariousAvailability_exits}
     *
     * @return array[] containing
     * module manager available flag
     * update available flag
     * and expected result of the tested method
     */
    public function execute_withVariousAvailabilityProvider()
    {
        return [
            [
                'moduleManagerAvailable' => false,
                'updateAvailable'        => true,
                'expectedResult'         => null,
            ],
            [
                'moduleManagerAvailable' => true,
                'updateAvailable'        => false,
                'expectedResult'         => null,
            ],
        ];
    }

    /**
     * @test
     * that execute adds new message into admin notification inbox when:
     * 1. Magento_AdminNotification module manager is enabled
     * 2. Admin notification collection size is equal to zero and admin notification inbox has severity 
     *
     * @covers ::execute
     *
     * @throws ReflectionException if adminNotificationCollectionFactory property doesn't exist
     */
    public function execute_whenAdminNotificationIsEnabled_addsAdminNotificationMessage()
    {
        $this->moduleManager->method('isEnabled')->with('Magento_AdminNotification')->willReturn(true);
        $latestVersion = '2.5';
        $this->updater->method('getVersion')->willReturn($latestVersion);
        $this->updater->method('getIsUpdateAvailable')->willReturn(true);
        $severity = MessageInterface::SEVERITY_CRITICAL;
        $this->updater->method('getSeverity')->willReturn($severity);
        $updateTitle = 'Stubbed title';
        $this->updater->method('getUpdateTitle')->willReturn($updateTitle);
        $moduleVersion = '1';
        $this->config->method('getModuleVersion')->willReturn($moduleVersion);
        $description = __(
            'Installed version: %1. Latest version: %2',
            $moduleVersion,
            $latestVersion
        );

        $adminNotificationCollectionFactory = $this->createPartialMock(CollectionFactory::class, ['create']);
        $adminNotificationCollection = $this->createMock(AdminNotificationInboxCollection::class);
        $adminNotificationCollectionFactory->expects(static::once())->method('create')
            ->willReturn($adminNotificationCollection);
        $adminNotificationCollection->expects(static::exactly(2))->method('addFieldToFilter')
            ->withConsecutive(['title', $updateTitle], ['description', $description])->willReturnSelf();
        $adminNotificationCollection->expects(static::once())->method('getSize')->willReturn(0);
        $this->adminNotificationInbox->expects(static::once())->method('add')
            ->with($severity, $updateTitle, $description, '', false);
        $this->adminNotificationInbox->expects(static::once())->method('getSeverities')->willReturn(__('critical'));

        TestHelper::setProperty(
            $this->currentMock,
            'adminNotificationCollectionFactory',
            $adminNotificationCollectionFactory
        );

        $this->currentMock->execute($this->observer);
    }
}