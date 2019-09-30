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

use http\Encoding\Stream;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\App\CacheInterface;



/**
 * Boltpay Metric Client Helper
 */

class MetricsClient extends AbstractHelper
{
    const METRICS_TIMESTAMP_ID = 'bolt_metrics_timestamp';
    // amount of microseconds between metrics posts
    const METRICS_POST_INTERVAL_MICROS = 30000000;

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
    private $headers;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @param Context $context
     * @param Config $configHelper
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManager
     * @param Bugsnag $bugsnag
     * @param LogHelper $logHelper
     * @param CacheInterface $cache
     *
     * @throws
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        DirectoryList $directoryList,
        StoreManagerInterface $storeManager,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        CacheInterface $cache
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->cache = $cache;
        //////////////////////////////////////////
        // Composerless installation.
        // Make sure libraries are in place:
        // lib/internal/Bolt/guzzle
        //////////////////////////////////////////
        if (!class_exists('\GuzzleHttp\Client')) {
            // phpcs:ignore
            require_once $directoryList->getPath('lib_internal') . '/Bolt/guzzle/autoloader.php';
        }
        $this->configHelper = $configHelper;
        $this->metricsFile = null;
        $this->guzzleClient = null;
        $this->metricsFile = null;


    }

    /**
     * Attempts to lock a file and returns a boolean based off of the result
     *
     * @param Stream        $workingFile    file that is attempting to be written to
     *
     * @return bool
     */
    protected function lockFile($workingFile){
        return flock($workingFile, LOCK_EX | LOCK_NB);
    }

    /**
     * Unlocks a file when finished
     *
     * @param Stream        $workingFile    file that is attempting to be written to
     *
     * @return void
     */
    protected function unlockFile($workingFile){
        flock($workingFile, LOCK_UN);    // release the lock
    }


    /**
     * Retrieves currrent time for when metrics are uploaded
     *
     * @return int
     */
    public function getCurrentTime() {
        return microtime(true) * 1000;
    }

    /**
     * Based off the environment the project is ran in, determines the endpoint to add merchant metrics
     *
     * @return \GuzzleHttp\Client
     */
    protected function setClient() {
        // determines if we are in the Sandbox env or not
        $base_uri = $this->configHelper->getApiUrl();

        // Creates a Guzzle Client and Headers needed for Metrics Requests
        return new \GuzzleHttp\Client(['base_uri' => $base_uri]);
    }

    /**
     * Retrieves Headers needed to communicate with Bolt
     *
     * @return array
     */
    protected function setHeaders() {
        return [
            'Content-Type' => 'application/json',
            'x-api-key' =>  $this->configHelper->getApiKey()
        ];
    }

    /**
     * Returns the amount of ms since the start time
     *
     * @return int
     */
    public function getElapsedTime($startTime) {
        return round((microtime(true) * 1000) - $startTime);
    }

    /**
     * Fetches the current root directory for magento and creates a bolt metrics file in that location
     *
     * @return string
     */
    protected function getFilePath() {
        // determine root directory and add create a metrics file there
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $rootPath  =  $directory->getRoot();
        return $rootPath . '/var/log/bolt_metrics.json';
    }

    /**
     * Attempts to open a file and if it cannot open in 5 seconds it will return null
     *
     * @return Stream
     */
    public function waitForFile(){
        $count = 0;
        $maxRetryCount = 10; //number of miliseconds of timeout (10 * 500) so 5 seconds

        if ($this->metricsFile == null) {
            $this->metricsFile = $this->getFilePath();
        }

        $workingFile = fopen($this->metricsFile, "a+");

        // logic for properly grabbing file and locking it
        while (!$this->lockFile($workingFile)) {
            if ($count++ < $maxRetryCount) {
                usleep(500);
            } else {
                return null;
                break;
            }
        }
        return $workingFile;
    }

    /**
     * Add a count metric to the array of metrics being stored
     *
     * @param string        $key    name of count metric
     * @param int           $value  count hit 
     *
     * @return Metric
     */
    public function formatCountMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "count",
                "timestamp" => round($this->getCurrentTime()),
            ];
        return new Metric($key, $data);
    }

     /**
     * Add a latency metric to the array of metrics being stored
     *
     * @param string        $key     name of latency metric
     * @param int           $value  the total time of the metric
     *
     * @return Metric
     */
    public function formatLatencyMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "latency",
                "timestamp" => round($this->getCurrentTime()),
            ];
        return new Metric($key, $data);

    }

    /**
     * Writes a metric to the metrics file
     *
     * @param Metric        $metric     metric and its data
     *
     * @return void
     */
    public function writeMetricToFile($metric)
    {
        if ($this->metricsFile == null) {
            $this->metricsFile = $this->getFilePath();
        }
        $workingFile = $this->waitForFile();
        if ($workingFile) {
            file_put_contents($this->metricsFile, [$metric->getMetricJson()], FILE_APPEND);
            file_put_contents($this->metricsFile, ",", FILE_APPEND);
            $this->unlockFile($workingFile);
            fclose($workingFile);
        }
    }

    /**
     * Adds a count metric to the metric file
     *
     * @param string        $countKey           name of count metric
     * @param int           $countValue         the count value of the metric
     *
     * @return void
     */
    public function processCountMetric($countKey, $countValue)
    {
        if (!$this->configHelper->shouldCaptureMetrics()) {
            return null;
        }
        $metric = $this->formatCountMetric($countKey, $countValue);

        $this->writeMetricToFile($metric);
    }

    /**
     * Adds a latency metric to the metric file
     *
     * @param string        $latencyKey         name of latency metric
     * @param mixed         $latencyStartTime  the total time of the metric
     *
     * @return void
     */
    public function processLatencyMetric($latencyKey, $latencyStartTime)
    {
        if (!$this->configHelper->shouldCaptureMetrics()) {
            return null;
        }
        $latencyValue = $this->getElapsedTime($latencyStartTime);
        $metric = $this->formatLatencyMetric($latencyKey, $latencyValue);

        $this->writeMetricToFile($metric);
    }

    /**
     * Centralized logic for handling the count and latency for a metric
     *
     * @param string        $countKey           name of count metric
     * @param int           $countValue         the count value of the metric
     * @param string        $latencyKey         name of latency metric
     * @param mixed         $latencyStartTime  the total time of the metric
     *
     * @return void
     */
    public function processMetric($countKey, $countValue, $latencyKey, $latencyStartTime)
    {
        $this->processCountMetric($countKey, $countValue);
        $this->processLatencyMetric($latencyKey, $latencyStartTime);

        $this->postMetrics();
    }

    /**
     * Post Metrics Collected in File to Merchant Metrics Endpoint, returning a 200 response if successful
     *
     *
     * @return int
     */
    public function postMetrics()
    {
        // logic for properly grabbing file and locking it
        if (!$this->configHelper->shouldCaptureMetrics()) {
            return null;
        }
        $previousPostTime = $this->loadFromCache(self::METRICS_TIMESTAMP_ID);
        // checks if key exists
        if ($previousPostTime) {
            $timeDiff = 1000000 * (microtime(true)  - $previousPostTime);
            if ($timeDiff < self::METRICS_POST_INTERVAL_MICROS) {
                return null;
            }
        }
        $workingFile = null;
        try{
            if ($this->metricsFile == null) {
                $this->metricsFile = $this->getFilePath();
            }
            $workingFile = $this->waitForFile();
            if ($workingFile) {
                //takes file contents and puts it to appropriate posting format
                $raw_file = "[" . rtrim(file_get_contents($this->metricsFile), ",") . "]";
                $output = json_decode($raw_file, true);
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
                    $this->saveToCache(self::METRICS_TIMESTAMP_ID, microtime(true));
                }
                return $response->getStatusCode();
            } else {
                return null;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException(new \Exception("Merchant Metrics send error", 1, $e));
        } finally {
            if ($workingFile) {
                flock($workingFile, LOCK_UN);    // release the lock
                fclose($workingFile);
            }
        }
        return null;
    }

    /**
     * Load data from Magento cache
     *
     * @param string $identifier
     * @param bool $decode
     * @return bool|mixed|string
     */
    protected function loadFromCache($identifier, $decode = true)
    {
        $cached = $this->cache->load($identifier);
        if (!$cached) return false;
        return $decode ? json_decode($cached) : $cached;
    }
    /**
     * Save data to Magento cache
     *
     * @param mixed $data
     * @param string $identifier
     * @param int $lifeTime
     * @param bool $encode
     * @param array $tags
     */
    protected function saveToCache($identifier, $data, $tags = [], $lifeTime = null, $encode = true)
    {
        $data = $encode ? json_encode($data) : $data;
        $this->cache->save($data, $identifier, $tags, $lifeTime);
    }
}
