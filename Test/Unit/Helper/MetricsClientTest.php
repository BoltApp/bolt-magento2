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
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Metric;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\DirectoryList;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use org\bovigo\vfs\vfsStream;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

class MetricsClientTest extends TestCase
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * @var MetricsClient
     */
    private $currentMock;

   /**
    * @var ConfigHelper
    */
    private $configHelper;

   /**
    * @var StoreManagerInterface
    */
    protected $storeManager;

   /**
    * @var string
    */
    private $metricsFile;

   /**
    * @var LogHelper
    */
    private $logHelper;

   /**
    * @var Bugsnag
    */
    private $bugsnag;

    /**
     * @var int
     */
    private $timeStamp;

    /**
     * @var string
     */
    private $countKey;

    /**
     * @var int
     */
    private $countValue;

    /**
     * @var string
     */
    private $latencyKey;

    /**
     * @var int
     */
    private $latencyValue;

    /**
     * @var string
     */
    private $fileInput;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Decider
     */
    private $deciderMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        // common values
        $this->timeStamp = 1567541470604;
        $this->countKey = "test_count";
        $this->countValue = 2;
        $this->latencyKey = "test_latency";
        $this->latencyValue = 1234;
        $this->fileInput = '{"' . $this->countKey . '":{"value":' . $this->countValue . ',"metric_type":"count","timestamp":' . $this->timeStamp . '}},';

        // set up successful endpoint
        $successfulEndpoint = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar']),
            new Response(200, ['X-Foo' => 'Bar']),
        ]);
        $handler = HandlerStack::create($successfulEndpoint);
        $this->guzzleClient = new Client(['handler' => $handler]);

        // mock needed classes
        $this->context = $this->createMock(Context::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->storeManager = $this->getMockBuilder(StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->deciderMock = $this->createMock(Decider::class);

        // Partially Mock the Class we are tesing
        $methods = ['unlockFile', 'lockFile', 'getCurrentTime'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();


        $this->currentMock->method('getCurrentTime')
            ->will($this->returnValue($this->timeStamp));
    }

    /**
     * @test
     */
    public function formatCountMetric()
    {
        $data = [
            'value' => $this->countValue,
            "metric_type" => "count",
            "timestamp" => $this->timeStamp
        ];
        $expectedMetric = new Metric($this->countKey, $data);
        $result = $this->currentMock->formatCountMetric($this->countKey, $this->countValue);


        $this->assertEquals($expectedMetric, $result);
    }

    /**
     * @test
     */
    public function addMetricLatency()
    {
        $data = [
            'value' => $this->latencyValue,
            "metric_type" => "latency",
            "timestamp" => $this->timeStamp
        ];

        $expectedMetric = new Metric($this->latencyKey, $data);
        $result = $this->currentMock->formatLatencyMetric($this->latencyKey, $this->latencyValue);

        $this->assertEquals($expectedMetric, $result);
    }

    /**
     * @test
     */
    public function validWaitForFile()
    {
        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];
        $root = vfsStream::setup('root', null, $structure);


        $methods = ['getFilePath'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $outputFile = $this->currentMock->waitForFile();

        $this->assertNotNull($outputFile);
        fclose($outputFile);
    }

    /**
     * @test
     */
    public function inValidWaitForFile()
    {
        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);


        $methods = ['getFilePath', 'lockFile'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();

        $this->currentMock->method('lockFile')
            ->will($this->returnValue(false));

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));
        $this->assertNull($this->currentMock->waitForFile());
    }

    /**
     * @test
     */
    public function emptyWaitForFile()
    {
        $structure = [
            'test' => []
        ];

        $root = vfsStream::setup('root', null, $structure);


        $methods = ['getFilePath'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/nofile.json'));

        $outputFile = $this->currentMock->waitForFile();

        $this->assertNotNull($outputFile);
        fclose($outputFile);
    }

    /**
     * @test
     */
    public function validWriteMetricToFile()
    {
        $structure = [
            'test' => []
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/metrics.json', "a+");

        $data = [
            'value' => $this->countValue,
            "metric_type" => "count",
            "timestamp" => $this->timeStamp
        ];
        $testMetric = new Metric($this->countKey, $data);

        $this->initWriteMetricToFile();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/metrics.json'));

        $this->currentMock->method('getCurrentTime')
            ->will($this->returnValue($this->timeStamp));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));

        $this->currentMock->writeMetricToFile($testMetric);

        $this->assertEquals(
            $this->fileInput,
            $root->getChild('test/metrics.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function noFileWriteMetricToFile()
    {
        $structure = [
            'test' => []
        ];

        $root = vfsStream::setup('root', null, $structure);

        $this->initWriteMetricToFile();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/metrics.json'));

        $this->currentMock->method('getCurrentTime')
            ->will($this->returnValue($this->timeStamp));


        $this->currentMock->expects($this->once())->method('waitForFile')
            ->will($this->returnValue(null));


        $data = [
            'value' => $this->countValue,
            "metric_type" => "count",
            "timestamp" => $this->timeStamp
        ];
        $testMetric = new Metric($this->countKey, $data);
        $this->currentMock->writeMetricToFile($testMetric);

        $this->assertNull(
            $root->getChild('test/metrics.json')
        );
    }


    /**
     * @test
     */
    public function validProcessCountMetric()
    {
        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));


        $this->initProcessMetrics();

        $this->currentMock->expects($this->once())->method('writeMetricToFile');
        $this->currentMock->expects($this->once())->method('formatCountMetric');


        $this->currentMock->processCountMetric($this->countKey, $this->countValue);
    }

    /**
     * @test
     */
    public function validProcessLatencyMetric()
    {
        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));


        $this->initProcessMetrics();

        $this->currentMock->expects($this->once())->method('writeMetricToFile');
        $this->currentMock->expects($this->once())->method('formatLatencyMetric');


        $this->currentMock->processLatencyMetric($this->latencyKey, $this->latencyValue);
    }

    /**
     * @test
     */
    public function flagOffProcessCountMetric()
    {
        $this->configHelper->method('shouldCaptureMetrics')
            ->will($this->returnValue(false));

        $this->initProcessMetrics();

        $this->currentMock->expects($this->never())->method('formatCountMetric');
        $this->currentMock->expects($this->never())->method('writeMetricToFile');

        $this->currentMock->processCountMetric($this->countKey, $this->countValue);
    }

    /**
     * @test
     */
    public function flagOffProcessLatencyMetric()
    {
        $this->configHelper->method('shouldCaptureMetrics')
            ->will($this->returnValue(false));

        $this->initProcessMetrics();

        $this->currentMock->expects($this->never())->method('formatLatencyMetric');
        $this->currentMock->expects($this->never())->method('writeMetricToFile');

        $this->currentMock->processLatencyMetric($this->countKey, $this->countValue);
    }

    /**
     * @test
     */
    public function postValidMetrics()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->configHelper->expects($this->once())
            ->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));



        $this->assertEquals(200, $this->currentMock->postMetrics());
        $this->assertEquals(
            "",
            $root->getChild('test/valid.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function postMetrics_noWorkingFile()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->configHelper->expects($this->never())
            ->method('getApiKey');

        $this->currentMock->method('waitForFile')
            ->willReturn(null);

        $this->assertNull($this->currentMock->postMetrics());
    }

    /**
     * @test
     */
    public function postNoCacheBatchMetrics()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->currentMock->method('loadFromCache')
            ->will($this->returnValue(false));

        $this->configHelper->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));



        $this->assertEquals(200, $this->currentMock->postMetrics());
        $this->assertEquals(
            "",
            $root->getChild('test/valid.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function postOldBatchMetrics()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->currentMock->method('loadFromCache')
            ->will($this->returnValue(microtime(true) - 40000000));

        $this->configHelper->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));



        $this->assertEquals(200, $this->currentMock->postMetrics());
        $this->assertEquals(
            "",
            $root->getChild('test/valid.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function postRecentBatchMetrics()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->currentMock->method('loadFromCache')
            ->will($this->returnValue(microtime(true)));

        $this->configHelper->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));



        $this->assertNull($this->currentMock->postMetrics());
    }

    /**
     * @test
     */
    public function postInValidMetrics()
    {
        $unSuccessfulEndpoint = new MockHandler([
            new Response(422, ['Content-Length' => 0])
        ]);
        $handler = HandlerStack::create($unSuccessfulEndpoint);
        $this->guzzleClient = new Client(['handler' => $handler]);

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));

        $this->configHelper->expects($this->once())
            ->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));


        $this->assertNull($this->currentMock->postMetrics());
        $this->assertEquals(
            $this->fileInput,
            $root->getChild('test/valid.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function postMetricsBadEndpoint()
    {

        $invalidEndpoint = new MockHandler([
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $handler = HandlerStack::create($invalidEndpoint);
        $this->guzzleClient = new Client(['handler' => $handler]);

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root', null, $structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $this->initPostMetrics();

        $this->currentMock->method('getFilePath')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue($this->guzzleClient));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(true));


        $this->configHelper->expects($this->once())
            ->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));


        $this->assertNull($this->currentMock->postMetrics());
        $this->assertEquals(
            $this->fileInput,
            $root->getChild('test/valid.json')->getContent()
        );
    }

    /**
     * @test
     */
    public function flagOffPostMetrics()
    {
        $this->initPostMetrics();

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMetrics')
            ->will($this->returnValue(false));

        $this->currentMock->expects($this->never())->method('getFilePath');

        $this->currentMock->expects($this->never())->method('setClient');

        $this->configHelper->expects($this->never())->method('getApiKey');

        $this->currentMock->expects($this->never())->method('waitForFile');

        $this->assertNull($this->currentMock->postMetrics());
    }

    /**
     * @test
     */
    public function getFilePath()
    {
        $filePath = self::invokeInaccessibleMethod($this->currentMock, 'getFilePath');
        $this->assertTrue(strpos($filePath, '/var/log/bolt_metrics.json') !== false);
    }

    /**
     * @test
     */
    public function loadFromCache_notCached()
    {
        $this->cache
            ->expects(self::once())
            ->method('load')
            ->with(MetricsClient::METRICS_TIMESTAMP_ID)
            ->willReturn(null);
        $this->assertFalse(
            self::invokeInaccessibleMethod(
                $this->currentMock,
                'loadFromCache',
                [MetricsClient::METRICS_TIMESTAMP_ID]
            )
        );
    }

    /**
     * @test
     */
    public function loadFromCache_cached_decode()
    {
        $cachedValue = '{"key":"value"}';
        $this->cache
            ->expects(self::once())
            ->method('load')
            ->with(MetricsClient::METRICS_TIMESTAMP_ID)
            ->willReturn($cachedValue);
        $this->assertEquals(
            json_decode($cachedValue),
            self::invokeInaccessibleMethod(
                $this->currentMock,
                'loadFromCache',
                [MetricsClient::METRICS_TIMESTAMP_ID]
            )
        );
    }

    /**
     * @test
     */
    public function loadFromCache_cached_noDecode()
    {
        $cachedValue = '{"key":"value"}';
        $this->cache
            ->expects(self::once())
            ->method('load')
            ->with(MetricsClient::METRICS_TIMESTAMP_ID)
            ->willReturn($cachedValue);
        $this->assertEquals(
            $cachedValue,
            self::invokeInaccessibleMethod(
                $this->currentMock,
                'loadFromCache',
                [MetricsClient::METRICS_TIMESTAMP_ID, false]
            )
        );
    }

    /**
     * @test
     */
    public function processMetric()
    {
        $this->deciderMock->expects($this->once())->method('isMerchantMetricsEnabled')
            ->willReturn(true);

        $methods = ['processCountMetric', 'processLatencyMetric', 'postMetrics'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();
        $this->currentMock
            ->expects(self::once())
            ->method('processCountMetric')
            ->with($this->countKey, $this->countValue);
        $this->currentMock
            ->expects(self::once())
            ->method('processLatencyMetric')
            ->with($this->latencyKey, $this->latencyValue);
        $this->currentMock->expects(self::once())->method('postMetrics');

        $this->currentMock->processMetric($this->countKey, $this->countValue, $this->latencyKey, $this->latencyValue);
    }

    /**
     * @test
     */
    public function processMetric_disabledFeature()
    {
        $this->deciderMock->expects($this->once())->method('isMerchantMetricsEnabled')
            ->willReturn(false);

        $methods = ['processCountMetric', 'processLatencyMetric', 'postMetrics'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();
        $this->currentMock
            ->expects(self::never())
            ->method('processCountMetric');
        $this->currentMock
            ->expects(self::never())
            ->method('processLatencyMetric');
        $this->currentMock->expects(self::never())->method('postMetrics');

        $this->currentMock->processMetric($this->countKey, $this->countValue, $this->latencyKey, $this->latencyValue);
    }

    /**
     * @test
     */
    public function setClient()
    {
        $result = self::invokeInaccessibleMethod(
            $this->currentMock,
            'setClient'
        );
        $this->assertTrue($result instanceof \GuzzleHttp\Client);
    }

    /**
     * @test
     */
    public function getCurrentTime()
    {
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $time = $this->currentMock->getCurrentTime();
        $this->assertGreaterThan($this->timeStamp, $time);
    }

    private function initWriteMetricToFile()
    {
        $methods = ['getFilePath', 'waitForFile', 'getCurrentTime'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();
    }

    private function initProcessMetrics()
    {
        $methods = ['formatCountMetric', 'formatLatencyMetric', 'writeMetricToFile'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();
    }

    private function initPostMetrics()
    {
        $methods = ['getFilePath', 'waitForFile', 'unlockFile', 'setClient', 'loadFromCache'];
        $this->currentMock = $this->getMockBuilder(MetricsClient::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper,
                    $this->cache,
                    $this->deciderMock
                ]
            )
            ->getMock();
    }

    /**
     * Invoke a private / protected method of an object.
     *
     * @param  object      $object
     * @param  string      $method
     * @param  array       $args
     * @param  string|null $class
     * @return mixed
     * @throws \ReflectionException
     */
    private static function invokeInaccessibleMethod($object, $method, $args = [], $class = null)
    {
        if (is_null($class)) {
            $class = $object;
        }

        $method = new \ReflectionMethod($class, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
