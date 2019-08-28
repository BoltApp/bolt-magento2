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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
// use Spatie\Async\Pool;

/**
 * Boltpay Merchant Metrics wrapper helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class MerchantMetrics extends AbstractHelper
{
    const STAGE_DEVELOPMENT = 'development';
    const STAGE_PRODUCTION  = 'production';

    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;

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
    public $metrics;

     /**
     * @var array
     */
    private $headers;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context $context
     * @param Config $configHelper
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManager
     * @param Bugsnag $bugsnag
     * @param LogHelper $logHelper
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        DirectoryList $directoryList,
        StoreManagerInterface $storeManager,
        Bugsnag $bugsnag,
        LogHelper $logHelper
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        //////////////////////////////////////////
        // Composerless installation.
        // Make sure libraries are in place:
        // lib/internal/Bolt/bugsnag
        // lib/internal/Bolt/guzzle
        //////////////////////////////////////////
        if (!class_exists('\GuzzleHttp\Client')) {
            require_once $directoryList->getPath('lib_internal') . '/Bolt/guzzle/autoloader.php';
        }
        $this->configHelper = $configHelper;
        $this->metrics = array();
        $this->metricsFile = null;
        $this->guzzleClient = null;
        $this->metricsFile = null;


    }

    protected function getCurrentTime() {
        return round(microtime(true) * 1000);
    }

    protected function setClient() {
        // determines if we are in the Sandbox env or not

        $base_uri =  $this->configHelper->isSandboxModeSet() ? 'https://api-sandbox.bolt.com/' : 'https://api.bolt.com/' ;
        // Creates a Guzzle Client and Headers needed for Metrics Requests
        return new \GuzzleHttp\Client(['base_uri' => $base_uri]);
    }

    protected function setHeaders() {
        return [
            'Content-Type' => 'application/json',
            'x-api-key' =>  $this->configHelper->getApiKey()
        ];
    }

    protected function setFile() {
        // determine root directory and add create a metrics file there
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $rootPath  =  $directory->getRoot();
        return $rootPath . '/bolt_metrics.json';
    }

    protected function waitForFile(){
        $count = 0;
        $timeoutMsecs = 10; //number of miliseconds of timeout (10 * 500) so 5 seconds

        $workingFile = fopen($this->metricsFile, "a+");

        // logic for properly grabbing file and locking it
        while (!flock($workingFile, LOCK_EX | LOCK_NB)) {
            if ($count++ < $timeoutMsecs) {
                usleep(500);
            } else {
                return null;
                break;
            }
        }
        return $workingFile;
    }

    public function unlockFile($workingFile){
        flock($workingFile, LOCK_UN);    // release the lock
    }


    /**
     * Add a count metric to the array of metrics being stored
     *
     * @param string        $key    name of count metric
     * @param int           $value  count hit 
     *
     * @return void
     */
    public function addCountMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "count",
                "timestamp" => $this->getCurrentTime(),
            ];
        $this->metrics[$key] = $data;
    }

     /**
     * Add a latency metric to the array of metrics being stored
     *
     * @param string        $key     name of latency metric
     * @param int           $value  the total time of the metric
     *
     * @return void
     */
    public function addLatencyMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "latency",
                "timestamp" => $this->getCurrentTime(),
            ];
        $this->metrics[$key] = $data;

    }

    /**
     * Adds a count and latency for a given event
     *
     * @param string        $countKey           name of count metric
     * @param int           $countValue         the count value of the metric
     * @param string        $latencyKey         name of latency metric
     * @param int           $latencyValue  the total time of the metric
     * @return void
     */
    public function processMetrics($countKey, $countValue, $latencyKey, $latencyValue)
    {


        // checks if flag is set for Merchant Metrics if not ignore
        if ($this->configHelper->shouldCaptureMerchantMetrics()) {
            $this->addCountMetric($countKey, $countValue);
            $this->addLatencyMetric($latencyKey, $latencyValue);

            if ($this->metricsFile == null) {
                $this->metricsFile = $this->setFile();
            }
            $workingFile = $this->waitForFile();
            if ($workingFile) {
                file_put_contents($this->metricsFile, json_encode($this->metrics), FILE_APPEND);
                file_put_contents($this->metricsFile, ",", FILE_APPEND);
                $this->unlockFile($workingFile);

            }
            fclose($workingFile);
        }
    }

    // to be used at a later time if threading is needed 
    // public function processMetricsThread($countKey, $countValue, $latencyKey, $latencyValue)
    // {
    //     $this->logHelper->addInfoLog($countKey);
    //     $pool = Pool::create();

    //     $pool[] = async(function () use ($countKey, $countValue, $latencyKey, $latencyValue) {
    //         $this->processMetrics($countKey, $countValue, $latencyKey, $latencyValue);
    //     });
        
    //     await($pool); // figure out how to manage multiple cases
    // }



    /**
     * Regsier a new notification callback.
     *
     *
     * @return void
     */
    public function postMetrics()
    {
        // logic for properly grabbing file and locking it
        if ($this->configHelper->shouldCaptureMerchantMetrics()) {
            try{
                $output = "";
                if ($this->metricsFile == null) {
                    $this->metricsFile = $this->setFile();
                }
                $workingFile = $this->waitForFile();
                if ($workingFile) {
                    //takes file contents and puts it to appropriate posting format
                    $raw_file = "[" . rtrim(file_get_contents($this->metricsFile), ",") . "]";
                    $output = json_decode($raw_file, true);
                    
                }
                if ($this->guzzleClient == null) {
                    $this->guzzleClient = $this->setClient();
                    $this->headers = $this->setHeaders();
                }

                $outputMetrics = ['metrics' => $output];
                $response = $this->guzzleClient->post("v1/merchant/metrics", [
                    'headers' => $this->headers,
                    'json' => $outputMetrics,
                ]);

                // Clear File if successfully posted
                if ($response->getStatusCode() == 200) {
                    file_put_contents($this->metricsFile, ""); 
                }
                return $response->getStatusCode();
            } catch (\Exception $e) {
                $this->bugsnag->notifyException(new Exception("Merchant Metrics send error", $e));
            } finally {
                flock($workingFile, LOCK_UN);    // release the lock
                fclose($workingFile);
            }
        }
    }
}
