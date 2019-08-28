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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;


use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use PHPUnit\Framework\TestCase;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MerchantMetrics;
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


class MerchantMetricsTest extends TestCase
{
    /**
    * @var \GuzzleHttp\Client
    */
   private $guzzleClient;

    /**
     * @var MerchantMetrics
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
    * @var array
    */
   private $Metrics;

    /**
    * @var array
    */
   private $Headers;

   /**
    * @var Bugsnag
    */
   private $bugsnag;


    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->timeStamp = 1567541470604;

        $this->countKey = "test_count";
        $this->countValue = 2;
        $this->latencyKey = "test_latency";
        $this->latencyValue = 1234;
        $this->fileInput = '{"' . $this->countKey . '":{"value":' . $this->countValue . ',"metric_type":"count","timestamp":' . $this->timeStamp . '},"' . $this->latencyKey . '":{"value":' . $this->latencyValue . ',"metric_type":"latency","timestamp":' . $this->timeStamp . '}},';

        $this->context = $this->createMock(Context::class);
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar']),
            new Response(202, ['Content-Length' => 0])
//            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $handler = HandlerStack::create($mock);
        $this->guzzleClient = new Client(['handler' => $handler]);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->directoryList = $this->createMock(DirectoryList::class);

        $this->storeManager = $this->getMockBuilder(StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->logHelper = $this->createMock(LogHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);


        $methods = ['unlockFile', 'getCurrentTime'];
        $this->currentMock = $this->getMockBuilder(MerchantMetrics::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper
                ]
            )
            ->getMock();


        $this->currentMock->method('getCurrentTime')
            ->will($this->returnValue($this->timeStamp));

        $this->configHelper->method('shouldCaptureMerchantMetrics')
            ->will($this->returnValue(true));
    }

    /**
     * @inheritdoc
     */

    public function testAddMetricCount()
    {
        $data = [
            'value' => $this->countValue,
            "metric_type" => "count",
            "timestamp" => $this->timeStamp
        ];
        $expectedKey = array();
        $expectedKey[$this->countKey] = $data;
        $this->currentMock->addCountMetric($this->countKey, $this->countValue);


        $this->assertEquals($expectedKey,  $this->currentMock->metrics);
    }

    public function testAddMetricLatency()
    {
        $data = [
            'value' => $this->latencyValue,
            "metric_type" => "latency",
            "timestamp" => $this->timeStamp
        ];
        $expectedKey = array();
        $expectedKey["test"] = $data;

        $this->currentMock->addLatencyMetric("test", $this->latencyValue);

        $this->assertEquals($expectedKey,  $this->currentMock->metrics);
    }


    public function testPostValidMetrics()
    {

        $structure = [
            'test' => [
                'valid.json' => $this->fileInput,
            ]
        ];

        $root = vfsStream::setup('root',null,$structure);

        $workingFile = fopen($root->url() . '/test/valid.json', "a+");

        $methods = ['setFile', 'waitForFile', 'unlockFile', 'setClient'];
        $this->currentMock = $this->getMockBuilder(MerchantMetrics::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper
                ]
            )
            ->getMock();

        $this->currentMock->method('setFile')
            ->will($this->returnValue($root->url() . '/test/valid.json'));

        $this->currentMock->method('setClient')
            ->will($this->returnValue( $this->guzzleClient));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMerchantMetrics')
            ->will($this->returnValue(true));

        $this->configHelper->expects($this->once())
            ->method('getApiKey')
            ->will($this->returnValue("60c47bdb25b0b133840808ce5fd2879d6295c53d0265c70e311552fb2028b00b"));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));


        $this->assertEquals(200,  $this->currentMock->postMetrics());
    }


    /**
     * @inheritdoc
     */
    public function testProcessMetrics()
    {
        $structure = [
            'test' => []
        ];

        $root = vfsStream::setup('root',null,$structure);

        $workingFile = fopen($root->url() . '/test/metrics.json', "a+");

        $methods = ['setFile', 'waitForFile', 'unlockFile', 'getCurrentTime'];
        $this->currentMock = $this->getMockBuilder(MerchantMetrics::class)
            ->setMethods($methods)
            ->enableOriginalConstructor()
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->directoryList,
                    $this->storeManager,
                    $this->bugsnag,
                    $this->logHelper
                ]
            )
            ->getMock();

        $this->currentMock->method('setFile')
            ->will($this->returnValue($root->url() . '/test/metrics.json'));

        $this->currentMock->method('getCurrentTime')
            ->will($this->returnValue($this->timeStamp));

        $this->configHelper->expects($this->once())
            ->method('shouldCaptureMerchantMetrics')
            ->will($this->returnValue(true));

        $this->currentMock->method('waitForFile')
            ->will($this->returnValue($workingFile));


        $this->currentMock->processMetrics($this->countKey, $this->countValue, $this->latencyKey, $this->latencyValue);

        $this->assertEquals(
            $this->fileInput,
            $root->getChild('test/metrics.json')->getContent()
        );

    }

}