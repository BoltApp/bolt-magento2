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

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\Updater;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\Package\Locker as ComposerPackageLocker;
use Composer\Package\Package as ComposerPackage;
use Composer\Package\PackageInterface as ComposerPackageInterface;
use Composer\Package\Version\VersionSelector as ComposerPackageVersionSelector;
use Composer\Repository\ComposerRepository as ComposerRepository;
use Composer\Repository\RepositoryManager as ComposerRepositoryManager;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Composer\ComposerFactory;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\MessageInterface as NotificationMessage;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Updater
 */
class UpdaterTest extends TestCase
{
    /**
     * @var array test update data
     */
    const UPDATE_DATA = [
        'is_update_available' => true,
        'severity'            => NotificationMessage::SEVERITY_NOTICE,
        'update_title'        => 'Bolt version 1.9.0 is now available!',
        'version'             => '1.9.0',
        'type'                => Updater::INSTALLATION_TYPE_MAGENTO_WEB_SETUP,
    ];
    /**
     * @var ComposerFactory|MockObject mocked instance of the Composer Factory class
     */
    private $composerFactory;

    /**
     * @var CacheInterface|MockObject mocked instance of the App Cache interface
     */
    private $cache;

    /**
     * @var Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnag;

    /**
     * @var Config|MockObject mocked instance of the Bolt Config helper
     */
    private $boltConfig;

    /**
     * @var Updater|MockObject mocked instance of the Bolt Updater model class
     */
    private $currentMock;

    /**
     * @var Composer|MockObject mocked instance of the Composer class
     */
    private $composerMock;

    /**
     * @var ComposerConfig|MockObject mocked instance of the Composer Config class
     */
    private $composerConfigMock;

    /**
     * @var ComposerRepositoryManager|MockObject mocked instance of the Composer Repository Manager class
     */
    private $composerRepositoryManagerMock;

    /**
     * @var Json instance of the Json Serializer class
     */
    private $serializer;

    /**
     * @var Decider|MockObject mocked instance of the Bolt Feature Switch helper
     */
    private $deciderHelper;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    protected function setUp()
    {
        $om = new ObjectManager($this);
        $this->composerFactory = $this->createMock(ComposerFactory::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->boltConfig = $this->createMock(Config::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->serializer = $om->getObject(Json::class);
        $this->deciderHelper = $this->createMock(Decider::class);
        $this->currentMock = $om->getObject(
            Updater::class,
            [
                'composerFactory' => $this->composerFactory,
                'cache'           => $this->cache,
                'serializer'      => $this->serializer,
                'boltConfig'      => $this->boltConfig,
                'bugsnag'         => $this->bugsnag,
                'deciderHelper'   => $this->deciderHelper,
            ]
        );
        $this->composerMock = $this->createMock(Composer::class);
        $this->composerConfigMock = $this->createMock(ComposerConfig::class);
        $this->composerMock->method('getConfig')->willReturn($this->composerConfigMock);
        $this->composerRepositoryManagerMock = $this->createMock(ComposerRepositoryManager::class);
        $this->composerMock->method('getRepositoryManager')->willReturn($this->composerRepositoryManagerMock);
    }

    /**
     * @test
     * that constructor sets properties to provided values
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new Updater(
            $this->composerFactory,
            $this->cache,
            $this->serializer,
            $this->boltConfig,
            $this->bugsnag,
            $this->deciderHelper
        );
        static::assertAttributeEquals($this->composerFactory, 'composerFactory', $instance);
        static::assertAttributeEquals($this->cache, 'cache', $instance);
        static::assertAttributeEquals($this->boltConfig, 'boltConfig', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
    }

    /**
     * @test
     * that getComposer returns the same instance on consecutive calls
     *
     * @covers ::getComposer
     *
     * @throws Exception from tested method
     */
    public function getComposer_onConsecutiveCalls_returnsSingleInstance()
    {
        static::assertAttributeEquals(null, '_composer', $this->currentMock);
        $this->composerFactory->expects(static::once())->method('create')->willReturnSelf();
        static::assertEquals($this->composerFactory, $this->currentMock->getComposer());
        static::assertAttributeEquals($this->composerFactory, '_composer', $this->currentMock);
        static::assertEquals($this->composerFactory, $this->currentMock->getComposer());
    }

    /**
     * Setup method for tests covering {@see Updater::getUpdate}
     *
     * @return Updater|MockObject
     */
    public function getUpdateSetUp()
    {
        return $this->getMockBuilder(Updater::class)
            ->setMethods(['checkForUpdate'])
            ->setConstructorArgs(
                [
                    $this->composerFactory,
                    $this->cache,
                    $this->serializer,
                    $this->boltConfig,
                    $this->bugsnag,
                    $this->deciderHelper
                ]
            )
            ->getMock();
    }

    /**
     * @test
     * that getUpdate returns update data from {@see \Bolt\Boltpay\Model\Updater::checkForUpdate} after caching it
     *
     * @covers ::getUpdate
     */
    public function getUpdate_withEmptyCache_checksForUpdate()
    {
        $currentMock = $this->getUpdateSetUp();
        $this->cache->expects(static::once())->method('load')->with(Updater::CACHE_IDENTIFIER)->willReturn(null);
        $this->cache->expects(static::once())->method('save')->with(
            $this->serializer->serialize(self::UPDATE_DATA),
            Updater::CACHE_IDENTIFIER,
            [],
            86400
        );
        $currentMock->expects(static::once())->method('checkForUpdate')->willReturn(self::UPDATE_DATA);
        static::assertEquals(self::UPDATE_DATA, $currentMock->getUpdate());
    }

    /**
     * @test
     * that getUpdate returns empty array and notifies exception if one occurs during update checking
     *
     * @covers ::getUpdate
     */
    public function getUpdate_whenExceptionOccursDuringUpdateChecking_notifiesExceptionAndReturnsEmptyArray()
    {
        $currentMock = $this->getUpdateSetUp();
        $this->cache->expects(static::once())->method('load')->with(Updater::CACHE_IDENTIFIER)->willReturn(null);
        $this->cache->expects(static::never())->method('save');
        $currentMock->expects(static::once())->method('checkForUpdate')
            ->willThrowException(new Exception('Unable to retrieve update'));
        static::assertEquals([], $currentMock->getUpdate());
    }

    /**
     * @test
     * that getUpdate returns update data from cache if present under {@see Updater::CACHE_IDENTIFIER}
     *
     * @covers ::getUpdate
     */
    public function getUpdate_withCachedUpdateData_returnsUpdateDataFromCache()
    {
        $currentMock = $this->getUpdateSetUp();
        $this->cache->expects(static::once())->method('load')
            ->with(Updater::CACHE_IDENTIFIER)
            ->willReturn($this->serializer->serialize(self::UPDATE_DATA));
        $this->cache->expects(static::never())->method('save');
        $currentMock->expects(static::never())->method('checkForUpdate');
        static::assertEquals(self::UPDATE_DATA, $currentMock->getUpdate());
    }

    /**
     * Setup method for tests covering {@see Updater::getData}
     *
     * @return Updater|MockObject
     */
    protected function getDataSetUp()
    {
        return $this->getMockBuilder(Updater::class)
            ->setMethods(['getUpdate', 'setData'])
            ->setConstructorArgs(
                [
                    $this->composerFactory,
                    $this->cache,
                    $this->serializer,
                    $this->boltConfig,
                    $this->bugsnag,
                    $this->deciderHelper
                ]
            )
            ->getMock();
    }

    /**
     * @test
     * that getData sets data to data returned from {@see \Bolt\Boltpay\Model\Updater::getUpdate}
     *
     * @covers ::getData
     *
     * @throws ReflectionException if _updateRetrieved property is undefined
     */
    public function getData_ifUpdateNotRetrieved_getsUpdate()
    {
        $currentMock = $this->getDataSetUp();
        TestHelper::setProperty($currentMock, '_updateRetrieved', false);
        $currentMock->expects(static::once())->method('getUpdate')->willReturn(self::UPDATE_DATA);
        $currentMock->expects(static::once())->method('setData')->with(self::UPDATE_DATA);
        $currentMock->getData('is_update_available');
        static::assertAttributeEquals(true, '_updateRetrieved', $currentMock);
    }

    /**
     * @test
     * that getData sets data to data returned from {@see \Bolt\Boltpay\Model\Updater::getUpdate}
     *
     * @covers ::getData
     *
     * @throws ReflectionException if _updateRetrieved property is undefined
     */
    public function getData_ifUpdateAlreadyRetrieved_doesNotGetUpdate()
    {
        $currentMock = $this->getDataSetUp();
        TestHelper::setProperty($currentMock, '_updateRetrieved', true);
        $currentMock->expects(static::never())->method('getUpdate');
        $currentMock->expects(static::never())->method('setData');
        $currentMock->getData('is_update_available');
    }

    /**
     * @test
     * that checkForUpdate checks availabilty of an update for the Bolt module
     *
     * @covers ::checkForUpdate
     *
     * @dataProvider checkForUpdate_withVariousUpdateStatesProvider
     *
     * @param array|false                    $package lock, stubbed result of {@see Config::getPackageLock}
     * @param ComposerPackageInterface|false $hasPatch either patch package for the current version, or false
     * @param ComposerPackageInterface|false $updatePackage either update package for the current version
     *                                                      or false if not available
     * @param string                         $currentVersion of the Boltpay module
     * @param bool                           $shouldDisableNotificationsForNonCriticalUpdates config value
     * @param bool                           $isNewReleaseNotificationsEnabled feature switch value
     * @param array                          $expectedResult of the method call
     *
     * @throws ReflectionException if boltConfig property is undefined
     */
    public function checkForUpdate_withVariousUpdateStates_returnsUpdateData(
        $package,
        $hasPatch,
        $updatePackage,
        $currentVersion,
        $shouldDisableNotificationsForNonCriticalUpdates,
        $isNewReleaseNotificationsEnabled,
        $expectedResult
    ) {
        $currentMock = $this->createPartialMock(
            Updater::class,
            [
                'getPackageLock',
                'getVersionSelector'
            ]
        );
        TestHelper::setProperty($currentMock, 'boltConfig', $this->boltConfig);
        TestHelper::setProperty($currentMock, 'deciderHelper', $this->deciderHelper);
        $this->boltConfig->method('getModuleVersion')->willReturn($currentVersion);
        $this->boltConfig->method('getShouldDisableNotificationsForNonCriticalUpdates')
            ->willReturn($shouldDisableNotificationsForNonCriticalUpdates);
        $this->deciderHelper->method('isNewReleaseNotificationsEnabled')->willReturn($isNewReleaseNotificationsEnabled);

        $versionSelectorMock = $this->createMock(ComposerPackageVersionSelector::class);
        $currentMock->method('getVersionSelector')->willReturn($versionSelectorMock);
        $versionSelectorMock->method('findBestCandidate')->withConsecutive(
            [
                Config::BOLT_COMPOSER_NAME,
                sprintf('>%1$s ~%1$s', $currentVersion)
            ],
            [
                Config::BOLT_COMPOSER_NAME,
                sprintf('>%1$s', $currentVersion)
            ]
        )->willReturnOnConsecutiveCalls($hasPatch, $updatePackage);
        $this->boltConfig->method('getPackageLock')->with(Config::BOLT_COMPOSER_NAME)
            ->willReturn($package);
        static::assertEquals($expectedResult, TestHelper::invokeMethod($currentMock, 'checkForUpdate'));
    }

    /**
     * Data provider for {@see checkForUpdate_withVariousUpdateStates_returnsUpdateData}
     *
     * @return array[] containing package, has patch, update package, current version,
     * should disable notifications for non critical updates and expected result of the method call
     */
    public function checkForUpdate_withVariousUpdateStatesProvider()
    {
        return [
            'Feature switch disabled'                 => [
                'package'                                         => false,
                'hasPatch'                                        => false,
                'updatePackage'                                   => [],
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => false,
                'expectedResult'                                  => ['is_update_available' => false]
            ],
            'Bolt installed directly in app/code'                 => [
                'package'                                         => false,
                'hasPatch'                                        => false,
                'updatePackage'                                   => [],
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => ['is_update_available' => false]
            ],
            'No update available'                                 => [
                'package'                                         => [],
                'hasPatch'                                        => false,
                'updatePackage'                                   => false,
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => ['is_update_available' => false]
            ],
            'Web setup update type'                               => [
                'package'                                         => [
                    'dist' => [
                        'url' => 'https://repo.magento.com/archives/boltpay/bolt-magento2/boltpay-bolt-magento2-1.0.0.0.zip'
                    ]
                ],
                'hasPatch'                                        => false,
                'updatePackage'                                   => new ComposerPackage(
                    Config::BOLT_COMPOSER_NAME,
                    '1.9.0.0',
                    '1.9.0'
                ),
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => self::UPDATE_DATA
            ],
            'Default composer update type'                        => [
                'package'                                         => [
                    'dist' => [
                        'url' => 'https://api.github.com/repos/BoltApp/bolt-magento2/zipball/4e3e5f5d4433cee90ab7c7f5ae96f2c97d18eec0'
                    ]
                ],
                'hasPatch'                                        => false,
                'updatePackage'                                   => new ComposerPackage(
                    Config::BOLT_COMPOSER_NAME,
                    '2.6.0.0',
                    '2.6.0'
                ),
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => [
                    'is_update_available' => true,
                    'severity'            => NotificationMessage::SEVERITY_MAJOR,
                    'update_title'        => __('Bolt version %1 is now available!', '2.6.0'),
                    'version'             => '2.6.0',
                    'type'                => Updater::INSTALLATION_TYPE_DEFAULT_COMPOSER,
                ]
            ],
            'Default composer update type with a patch available' => [
                'package'                                         => [
                    'dist' => [
                        'url' => 'https://api.github.com/repos/BoltApp/bolt-magento2/zipball/4e3e5f5d4433cee90ab7c7f5ae96f2c97d18eec0'
                    ]
                ],
                'hasPatch'                                        => true,
                'updatePackage'                                   => new ComposerPackage(
                    Config::BOLT_COMPOSER_NAME,
                    '2.6.0.0',
                    '2.6.0'
                ),
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => [
                    'is_update_available' => true,
                    'severity'            => NotificationMessage::SEVERITY_CRITICAL,
                    'update_title'        => __('Bolt version %1 is available to address a CRITICAL issue.', '2.6.0'),
                    'version'             => '2.6.0',
                    'type'                => Updater::INSTALLATION_TYPE_DEFAULT_COMPOSER,
                ]
            ],
            'Default composer update patch'                       => [
                'package'                                         => [
                    'dist' => [
                        'url' => 'https://api.github.com/repos/BoltApp/bolt-magento2/zipball/4e3e5f5d4433cee90ab7c7f5ae96f2c97d18eec0'
                    ]
                ],
                'hasPatch'                                        => true,
                'updatePackage'                                   => new ComposerPackage(
                    Config::BOLT_COMPOSER_NAME,
                    '2.6.1.0',
                    '2.6.1'
                ),
                'currentVersion'                                  => '2.6.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => false,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => [
                    'is_update_available' => true,
                    'severity'            => NotificationMessage::SEVERITY_CRITICAL,
                    'update_title'        => __('Bolt version %1 is available to address a CRITICAL issue.', '2.6.1'),
                    'version'             => '2.6.1',
                    'type'                => Updater::INSTALLATION_TYPE_DEFAULT_COMPOSER,
                ]
            ],
            'Non critical update notifications disabled'          => [
                'package'                                         => [
                    'dist' => [
                        'url' => 'https://api.github.com/repos/BoltApp/bolt-magento2/zipball/4e3e5f5d4433cee90ab7c7f5ae96f2c97d18eec0'
                    ]
                ],
                'hasPatch'                                        => false,
                'updatePackage'                                   => new ComposerPackage(
                    Config::BOLT_COMPOSER_NAME,
                    '1.1.0.0',
                    '1.1.0'
                ),
                'currentVersion'                                  => '1.0.0',
                'shouldDisableNotificationsForNonCriticalUpdates' => true,
                'isNewReleaseNotificationsEnabled'                => true,
                'expectedResult'                                  => [
                    'is_update_available' => false
                ]
            ],
        ];
    }

    /**
     * @test
     * that getVersionSelector returns instance of {@see \Composer\Package\Version\VersionSelector}
     *
     * @dataProvider getVersionSelector_withVariousUpdateTypesProvider
     *
     * @covers ::getVersionSelector
     *
     * @param array $defaultRepositoryConfig of composer
     * @param bool $updateEnabled flag for {@see \Bolt\Boltpay\Helper\FeatureSwitch\Decider::isUseGithubForUpdateEnabled}
     * @param bool $expectException whether to expect exception
     *
     * @throws ReflectionException if getVersionSelector method is undefined
     */
    public function getVersionSelector_withVariousUpdateAva_returnsVersionSelector(
        $defaultRepositoryConfig,
        $updateEnabled,
        $expectException
    ) {
        $repositoriesConfig = [
            0 => $defaultRepositoryConfig,
            ComposerInformation::COMPOSER_DEFAULT_REPO_KEY => $defaultRepositoryConfig,
        ];
        $this->composerFactory->method('create')->willReturn($this->composerMock);
        $this->composerConfigMock->expects(static::once())->method('getRepositories')->willReturn($repositoriesConfig);
        $this->deciderHelper->method('isUseGithubForUpdateEnabled')->willReturn($updateEnabled);
        $composerRepositoryMock = $this->createMock(ComposerRepository::class);
        $composerRepositoryMock->method('getPackages')->willReturn([]);
        if ($expectException) {
            $this->expectException(LocalizedException::class);
            $this->expectExceptionMessage('Unable to select composer repository');
        }
        $this->composerRepositoryManagerMock->expects($expectException ? static::never() : static::once())
            ->method('createRepository')
            ->with(
                'composer',
                $updateEnabled ? $repositoriesConfig[ComposerInformation::COMPOSER_DEFAULT_REPO_KEY] : $defaultRepositoryConfig
            )
            ->willReturn($composerRepositoryMock);
        /** @var ComposerPackageVersionSelector $result */
        $result = TestHelper::invokeMethod($this->currentMock, 'getVersionSelector', []);
        static::assertInstanceOf(ComposerPackageVersionSelector::class, $result);
    }

    /**
     * Data provider for {@see getVersionSelector_withVariousUpdateTypes_returnsVersionSelector}
     *
     * @return array[] containing default repository config, Bolt update enabled flag and expected exception
     */
    public function getVersionSelector_withVariousUpdateTypesProvider()
    {
        $defaultRepositoryConfig = [
            'type' => 'composer',
            'url'  => 'https://repo.magento.com/',
        ];
        
        return [
            [
                'defaultRepositoryConfig' => $defaultRepositoryConfig,
                'updateEnabled' => true,
                'expectException' => false,
            ],
            [
                'defaultRepositoryConfig' => $defaultRepositoryConfig,
                'updateEnabled' => false,
                'expectException' => false,
            ],
            [
                'defaultRepositoryConfig' => [],
                'updateEnabled' => true,
                'expectException' => true,
            ]
        ];
    }

    /**
     * @test
     * that isUrlMagentoRepo returns true if provided URL is related to Magento composer repository, otherwise false
     *
     * @covers ::isUrlMagentoRepo
     *
     * @dataProvider isUrlMagentoRepo_withVariousUrlsProvider
     *
     * @param string $url to be checked
     * @param bool $expectedResult of the tested method call
     *
     * @throws ReflectionException
     */
    public function isUrlMagentoRepo_withVariousUrls_returnsBoolValue($url, $expectedResult)
    {
        static::assertEquals($expectedResult, TestHelper::invokeMethod($this->currentMock, 'isUrlMagentoRepo', [$url]));
    }

    /**
     * Data provider for {@see isUrlMagentoRepo_withVariousUrls_returnsBoolValue}
     *
     * @return array[] containing url and expected result
     */
    public function isUrlMagentoRepo_withVariousUrlsProvider()
    {
        return [
            [
                'url' => 'https://api.github.com/repos/BoltApp/bolt-magento2/zipball/4e3e5f5d4433cee90ab7c7f5ae96f2c97d18eec0',
                'expectedResult' => false,
            ],
            [
                'url' => 'https://repo.magento.com/archives/boltpay/bolt-magento2/boltpay-bolt-magento2-1.0.0.0.zip',
                'expectedResult' => true,
            ],
        ];
    }
}
