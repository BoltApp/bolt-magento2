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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bugsnag\Client as BugsnagClient;
use Bugsnag\Report;
use Exception;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Bugsnag;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use ReflectionException;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\Bugsnag
 */
class BugsnagTest extends TestCase
{
    /** @var string Dummy store url */
    const STORE_URL = 'http://store.local/';
    const COMPOSER_VERSION = 'composer_version';

    /** @var MockObject|Bugsnag mocked instance of the class tested */
    private $currentMock;

    /** @var MockObject|BugsnagClient mocked instance of Bugsnag client */
    private $bugsnagMock;

    /** @var MockObject|StoreManagerInterface mocked instance of the Store manager */
    private $storeManagerMock;

    /** @var MockObject|Context mocked instance of Helper context */
    private $contextMock;

    /** @var MockObject|Config mocked instance of the Config Helper */
    private $configHelperMock;

    /** @var MockObject|DirectoryList mocked instance of Directory List object */
    private $directoryListMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException if unable to set internal mock properties
     */
    protected function setUp()
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->configHelperMock = $this->createPartialMock(Config::class, ['getComposerVersion','isSandboxModeSet','isTestEnvSet','getModuleVersion']);
        $this->directoryListMock = $this->createMock(DirectoryList::class);
        $this->storeManagerMock = $this->createPartialMock(StoreManager::class, ['getStore', 'getBaseUrl']);
        $this->bugsnagMock = $this->createMock(BugsnagClient::class);

        $this->currentMock = $this->createPartialMock(Bugsnag::class, []);
        TestHelper::setProperty($this->currentMock, 'bugsnag', $this->bugsnagMock);
        TestHelper::setProperty(
            $this->currentMock,
            'storeManager',
            $this->storeManagerMock
        );

        TestHelper::setProperty(
            $this->currentMock,
            'configHelper',
            $this->configHelperMock
        );
    }

    /**
     * @test
     * that Bugsnag constructor creates Bugsnag client and sets release stage depending on whether sandbox mode is set,
     * that it sets configHelper and storeManager properties, and adds store url to Bugsnag metadata
     *
     * @covers ::__construct
     *
     * @dataProvider __construct_inVariousSandboxStates_setsAppropriateReleaseStageProvider
     *
     * @param bool   $isSandboxModeSet configuration flag
     * @param string $releaseStage expected to be set to Bugsnag client
     * @param bool   $testEnv to mock env variable or not
     */
    public function __construct_inVariousSandboxStates_setsAppropriateReleaseStage($isSandboxModeSet, $releaseStage, $testEnv)
    {
        $this->configHelperMock->expects(static::any())->method('isSandboxModeSet')->willReturn($isSandboxModeSet);
        $this->configHelperMock->expects(static::once())->method('isTestEnvSet')->willReturn($testEnv);
        $this->configHelperMock->expects(static::once())->method('getModuleVersion')->willReturn('2.1.0');

        $this->storeManagerMock->expects(static::any())->method('getStore')->willReturnSelf();
        $this->storeManagerMock->expects(static::any())->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)
            ->willReturn(self::STORE_URL);

        $instance = new Bugsnag(
            $this->contextMock,
            $this->configHelperMock,
            $this->directoryListMock,
            $this->storeManagerMock
        );
        static::assertAttributeEquals(
            $this->storeManagerMock,
            'storeManager',
            $instance
        );
        static::assertAttributeEquals(
            $this->configHelperMock,
            'configHelper',
            $instance
        );
        /** @var BugsnagClient $bugsnag */
        $bugsnag = static::readAttribute($instance, 'bugsnag');
        $bugsnag->getPipeline()->execute(
            $this->createPartialMock(Report::class, []),
            function ($report) {
                /** @var Report $report */
                static::assertEquals(self::STORE_URL, $report->getMetaData()['META DATA']['store_url']);
            }
        );
        static::assertInstanceOf(BugsnagClient::class, $bugsnag);
        static::assertEquals($bugsnag->getConfig()->getAppData()['releaseStage'], $releaseStage);
        static::assertEquals($bugsnag->getConfig()->getAppData()['version'], '2.1.0');
    }

    /**
     * Data provider for {@see __construct_inVariousSandboxStates_setsAppropriateReleaseStage}
     *
     * @return array containing sandbox mode flag and appropriate release stage
     */
    public function __construct_inVariousSandboxStates_setsAppropriateReleaseStageProvider()
    {
        return [
            ['isSandboxModeSet' => true, 'releaseStage' => Bugsnag::STAGE_DEVELOPMENT, 'testEnv' => false],
            ['isSandboxModeSet' => false, 'releaseStage' => Bugsnag::STAGE_PRODUCTION, 'testEnv' => false],
            ['isSandboxModeSet' => true, 'releaseStage' => Bugsnag::STAGE_TEST, 'testEnv' => true]
        ];
    }

    /**
     * @test
     * that notifyException executes {@see \Bugsnag\Client::notifyException}
     *
     * @covers ::notifyException
     */
    public function notifyException_always_executesBugsnagNotifyException()
    {
        $exception = new Exception('Expected exception message');
        $callback = function () {
        };
        $this->bugsnagMock->expects(static::once())->method('notifyException')->with($exception, $callback);
        $this->currentMock->notifyException($exception, $callback);
    }

    /**
     * @test
     * that notifyError executes {@see \Bugsnag\Client::notifyError}
     *
     * @covers ::notifyError
     */
    public function notifyError_always_callsBugsnagNotifyError()
    {
        $name = 'LocalizedException: ';
        $message = 'Dummy exception message';
        $callback = function () {
        };
        $this->bugsnagMock->expects(static::once())->method('notifyError')->with($name, $message, $callback);
        $this->currentMock->notifyError($name, $message, $callback);
    }

    /**
     * @test
     * that notifyError executes {@see \Bugsnag\Client::registerCallback}
     *
     * @covers ::registerCallback
     */
    public function registerCallback_always_callsBugsnagRegisterCallback()
    {
        $callback = function () {
        };
        $this->bugsnagMock->expects(static::once())->method('registerCallback')->with($callback);
        $this->currentMock->registerCallback($callback);
    }

    /**
     * @test
     * that addCommonMetaData registers callback that adds store url to metadata
     *
     * @covers ::addCommonMetaData
     *
     * @throws ReflectionException if addCommonMetaData method is not defined
     */
    public function addCommonMetaData_always_registersBugsnagCallback()
    {
        $this->storeManagerMock->expects(static::once())->method('getStore')->willReturnSelf();
        $this->storeManagerMock->expects(static::once())->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)
            ->willReturn(self::STORE_URL);
        $this->configHelperMock->expects(static::once())->method('getComposerVersion')->willReturn(self::COMPOSER_VERSION);
        $this->bugsnagMock->expects(static::once())->method('registerCallback')->with(
            static::callback(
                function ($callback) {
                    $reportMock = $this->createMock(Report::class);
                    $reportMock->expects(static::once())->method('addMetaData')->with(
                        [
                            'META DATA' => [
                                'store_url' => self::STORE_URL,
                                'composer_version' => self::COMPOSER_VERSION
                            ]
                        ]
                    );
                    $callback($reportMock);
                    return true;
                }
            )
        );
        TestHelper::invokeMethod($this->currentMock, 'addCommonMetaData');
    }
}
