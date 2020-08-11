<?php

namespace Bolt\Boltpay\Test\Unit\ViewModel;

use Bolt\Boltpay\Model\Updater;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\ViewModel\UpdatePopup;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\Model\Url;
use Magento\Framework\App\DocRootLocator;
use Magento\Framework\Module\Manager;
use Magento\Framework\Notification\MessageInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\ViewModel\UpdatePopup
 */
class UpdatePopupTest extends TestCase
{
    /**
     * @var Updater|MockObject of the Updater class
     */
    private $updater;

    /**
     * @var Session|MockObject of the Session Manager
     */
    private $session;

    /**
     * @var Manager|MockObject of the module Manager
     */
    private $moduleManager;

    /**
     * @var DocRootLocator|MockObject of the document root class
     */
    private $docRootLocator;

    /**
     * @var Url|MockObject of the Url model
     */
    private $url;

    /**
     * @var UpdatePopup|MockObject of the tested class
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
            ['getIsUpdateAvailable', 'getSeverity', 'getVersion']
        );
        $this->session = $this->createPartialMock(Session::class, ['isAllowed', 'setData', 'getData', 'getUser']);
        $this->moduleManager = $this->createMock(Manager::class);
        $this->docRootLocator = $this->createMock(DocRootLocator::class);
        $this->url = $this->createMock(Url::class);

        $this->currentMock = $this->getMockBuilder(UpdatePopup::class)
            ->setConstructorArgs(
                [
                    $this->updater,
                    $this->session,
                    $this->moduleManager,
                    $this->docRootLocator,
                    $this->url,
                ]
            )
            ->setMethods(null)
            ->getMock();
    }

    /**
     * @test
     * that __construct sets provided values to internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsProperties()
    {
        $instance = new UpdatePopup(
            $this->updater,
            $this->session,
            $this->moduleManager,
            $this->docRootLocator,
            $this->url
        );

        static::assertAttributeEquals($this->updater, 'updater', $instance);
        static::assertAttributeEquals($this->session, 'session', $instance);
        static::assertAttributeEquals($this->moduleManager, 'moduleManager', $instance);
        static::assertAttributeEquals($this->docRootLocator, 'docRootLocator', $instance);
        static::assertAttributeEquals($this->url, 'url', $instance);
    }

    /**
     * @test
     * that shouldDisplay returns true if:
     * 1. update is available
     * 2. module manager is enabled for Magento_AdminNotification
     * 3. session is allowed for Magento_AdminNotification::show_toolbar
     *
     * @covers ::shouldDisplay
     *
     * @dataProvider shouldDisplay_withVariousShouldDisplayConditionsProvider
     *
     * @param bool $isUpdateAvailable flag
     * @param bool $isAdminNotificationEnabled flag
     * @param bool $isNotificationAllowedACL flag
     * @param bool $isPopupAlreadyShown session flag
     */
    public function shouldDisplay_withVariousShouldDisplayConditions_returnsBoolValue(
        $isUpdateAvailable,
        $isAdminNotificationEnabled,
        $isNotificationAllowedACL,
        $isPopupAlreadyShown
    ) {
        $this->updater->method('getIsUpdateAvailable')->willReturn($isUpdateAvailable);
        $this->moduleManager->method('isEnabled')
            ->with('Magento_AdminNotification')
            ->willReturn($isAdminNotificationEnabled);

        $this->session->method('isAllowed')
            ->with('Magento_AdminNotification::show_toolbar')
            ->willReturn($isNotificationAllowedACL);

        $this->session->method('getData')
            ->with(UpdatePopup::BOLT_POPUP_SHOWN)
            ->willReturn($isPopupAlreadyShown);

        $shouldDisplay = $isUpdateAvailable && !$isPopupAlreadyShown
            && $isAdminNotificationEnabled && $isNotificationAllowedACL;

        $this->session->expects($shouldDisplay ? static::once() : static::never())
            ->method('setData')
            ->with(UpdatePopup::BOLT_POPUP_SHOWN, true);

        static::assertEquals($shouldDisplay, $this->currentMock->shouldDisplay());
    }

    /**
     * Data provider for {@see shouldDisplay_withVariousShouldDisplayConditions_returnsBoolValue}
     *
     * @return array[] containing flags:
     * 1. is update available
     * 2. is admin notification enabled
     * 3. is session allowed
     * and expected result of the tested method
     */
    public function shouldDisplay_withVariousShouldDisplayConditionsProvider()
    {
        return TestHelper::getAllBooleanCombinations(4);
    }

    /**
     * @test
     * that shouldDisplay saves user extra data when:
     * update is available
     * Magento_AdminNotification is enabled
     * Magento_AdminNotification::show_toolbar is allowed
     * Severity is notice {@see MessageInterface::SEVERITY_NOTICE}
     * Latest version is not in admin extra data
     *
     * @covers ::shouldDisplay
     *
     * @dataProvider shouldDisplay_withVariousSeveritiesProvider
     *
     * @param int    $severity message code
     * @param string $latestVersion of updater
     * @param mixed  $adminExtra data
     * @param bool   $expectedResult od the tested method
     * 
     * @throws ReflectionException if unable to set internal mock properties
     */
    public function shouldDisplay_withVariousSeverities_savesUserExtra(
        $severity,
        $latestVersion,
        $adminExtra,
        $expectedResult
    ) {
        $this->updater->method('getIsUpdateAvailable')->willReturn(true);
        $this->moduleManager->method('isEnabled')->with('Magento_AdminNotification')->willReturn(true);
        $this->session->method('isAllowed')->with('Magento_AdminNotification::show_toolbar')->willReturn(true);

        $noticeSeverity = $severity === MessageInterface::SEVERITY_NOTICE;
        $this->updater->method('getSeverity')->willReturn($severity);
        $adminUser = $this->createPartialMock(User::class, ['getExtra', 'saveExtra']);
        $this->session->expects($noticeSeverity ? static::once() : static::never())
            ->method('getUser')
            ->willReturn($adminUser);

        $adminUser->expects($noticeSeverity ? static::once() : static::never())
            ->method('getExtra')
            ->willReturn($adminExtra);

        $this->updater->expects($noticeSeverity ? static::once() : static::never())
            ->method('getVersion')
            ->willReturn($latestVersion);

        if (is_array($adminExtra) && !in_array($latestVersion, $adminExtra['bolt_minor_update_popups_shown'])) {
            $adminExtra['bolt_minor_update_popups_shown'][] = $latestVersion;
            $adminUser->expects(static::once())->method('saveExtra')->with($adminExtra);
        }

        static::assertEquals($expectedResult, $this->currentMock->shouldDisplay());
    }

    /**
     * Data provider for {@see shouldDisplay_withVariousSeverities_savesUserExtra}
     *
     * @return array[] containing
     * severity message code
     * latest updater version
     * admin extra data
     * expected result of the tested method
     */
    public function shouldDisplay_withVariousSeveritiesProvider()
    {
        return [
            [
                'severity'       => MessageInterface::SEVERITY_CRITICAL,
                'latestVersion'  => '',
                'adminExtra'     => null,
                'expectedResult' => true,
            ],
            [
                'severity'       => MessageInterface::SEVERITY_MAJOR,
                'latestVersion'  => '',
                'adminExtra'     => null,
                'expectedResult' => true,
            ],
            [
                'severity'       => MessageInterface::SEVERITY_MINOR,
                'latestVersion'  => '',
                'adminExtra'     => null,
                'expectedResult' => true,
            ],
            [
                'severity'       => MessageInterface::SEVERITY_NOTICE,
                'latestVersion'  => '2.2.1',
                'adminExtra'     => [
                    'bolt_minor_update_popups_shown' => [
                        '2.2.1'
                    ],
                ],
                'expectedResult' => false,
            ],
            [
                'severity'       => MessageInterface::SEVERITY_NOTICE,
                'latestVersion'  => '2.2.1',
                'adminExtra'     => [
                    'bolt_minor_update_popups_shown' => [],
                ],
                'expectedResult' => true,
            ],
            [
                'severity'       => MessageInterface::SEVERITY_NOTICE,
                'latestVersion'  => '2.2.1',
                'adminExtra'     => null,
                'expectedResult' => true,
            ],
        ];
    }

    /**
     * @test
     * that getSetupWizardUrl returns setup wizard URL if session is allowed for Magento_Backend::setup_wizard and
     * document root location is public
     *
     * @covers ::getSetupWizardUrl
     *
     * @dataProvider getSetupWizardUrl_withVariousWizardConditionsProvider
     *
     * @param bool  $sessionAvailability flag
     * @param bool  $docRootIsPublic flag
     * @param mixed $expectedResult of the tested method
     */
    public function getSetupWizardUrl_withVariousWizardConditions_returnsUrlOrNull(
        $sessionAvailability,
        $docRootIsPublic,
        $expectedResult
    ) {
        $this->session->method('isAllowed')->willReturn($sessionAvailability);
        $this->docRootLocator->method('isPub')->willReturn($docRootIsPublic);

        $this->url->expects($sessionAvailability && !$docRootIsPublic ? static::once() : static::never())
            ->method('getUrl')
            ->with('adminhtml/backendapp/redirect/app/setup')
            ->willReturn($expectedResult);

        static::assertEquals($expectedResult, $this->currentMock->getSetupWizardUrl());
    }
    
    /**
     * @test
     * that getReleaseDownloadLink returns the Github link to the archive of the provided Bolt plugin version
     *
     * @covers ::getReleaseDownloadLink
     */
    public function getReleaseDownloadLink_always_returnsGithubLink()
    {
        $version = '2.2';
        $expectedLink = 'https://github.com/BoltApp/bolt-magento2/archive/2.2.zip';
        static::assertEquals($expectedLink, $this->currentMock->getReleaseDownloadLink($version));
    }

    /**
     * Data provider for {@see getSetupWizardUrl_withVariousWizardConditions_returnsUrlOrNull}
     *
     * @return array[] containing flags for:
     * 1. session availability,
     * 2. document root is public
     * and expected result of the tested method
     */
    public function getSetupWizardUrl_withVariousWizardConditionsProvider()
    {
        return [
            ['sessionAvailability' => false, 'docRootIsPublic' => false, 'expectedResult' => null],
            ['sessionAvailability' => true, 'docRootIsPublic' => false, 'expectedResult' => null],
            ['sessionAvailability' => true, 'docRootIsPublic' => true, 'expectedResult' => null],
            ['sessionAvailability' => true, 'docRootIsPublic' => false, 'expectedResult' => '/app/setup'],
        ];
    }

    /**
     * @test
     * that getUpdater returns updater instance {@see Updater}
     *
     * @covers ::getUpdater
     *
     * @throws ReflectionException if updater property is undefined
     */
    public function getUpdater_always_returnsUpdater()
    {
        TestHelper::setProperty($this->currentMock, 'updater', $this->updater);
        static::assertEquals($this->updater, $this->currentMock->getUpdater());
    }
}
