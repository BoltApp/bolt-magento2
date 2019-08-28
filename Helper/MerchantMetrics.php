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
use Spatie\Async\Pool;
use Bolt\Boltpay\Helper\Log as LogHelper;

/**
 * Boltpay Bugsnag wrapper helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class MerchantMetrics extends AbstractHelper
{
    const API_KEY           = '888766c6cfe49858afc36b3a2a2c6548';
    const STAGE_DEVELOPMENT = 'development';
    const STAGE_PRODUCTION  = 'production';
    const METRICS_FILE  = '/Applications/MAMP/htdocs/magento2/metrics.json';

    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /* @var StoreManagerInterface */
    protected $storeManager;

    protected $metricsFile;

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
     * @param Context $context
     * @param Config $configHelper
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManager
     * @param Bugsnag $bugsnag
     * @param LogHelper                       $logHelper
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
        $this->metricsFile = '/Applications/MAMP/htdocs/magento2/metrics.json';
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

        // $release_stage = $this->configHelper->isSandboxModeSet() ? self::STAGE_DEVELOPMENT : self::STAGE_PRODUCTION;
        $this->guzzleClient = new \GuzzleHttp\Client(['base_uri' => 'http://api.ethan.dev.bolt.me/']);
        $this->Metrics = array();
        $this->Headers = [
            'Content-Type' => 'application/json',
            'x-api-key' =>  "0b543baeb28c872792d7ba34ba83960eecb63f5fdbc633bedb1880b0ede5101b" //$this->configHelper->getApiKey()
        ];
    }


    /**
     * @param        $exception
     * @param string $msg
     * @param int    $code
     * @param int    $httpStatusCode
     */
    protected function catchExceptionAndSendError($exception, $msg = '', $code = 6009, $httpStatusCode = 422)
    {
        $this->bugsnag->notifyException($exception);

        $this->sendErrorResponse($code, $msg, $httpStatusCode);
    }

    /**
     * Notify Bugsnag of a non-fatal/handled error.
     *
     * @param string        $key     the name of the error, a short (1 word) string
     * @param int           $value  the error message 
     *
     * @return void
     */
    public function addCountMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "count",
                "timestamp" => time()
            ];
        $this->Metrics[$key] = $data;
    }

     /**
     * Notify Bugsnag of a non-fatal/handled error.
     *
     * @param string        $key     the name of the error, a short (1 word) string
     * @param int           $value  the error message 
     *
     * @return void
     */
    public function addLatencyMetric($key, $value)
    {
        $data = [
                'value' => $value,
                "metric_type" => "latency",
                "timestamp" => time(),
            ];
        $this->Metrics[$key] = $data;
    }

    /**
     * Regsier a new notification callback.
     *
     *
     * @return void
     */

     // Add events directly to file with no storage, the file is the source of truth, add metrics one by one
     // Thread is to simply add data to file
     // Cron job running the post
    public function processMetrics($countKey, $countValue, $latencyKey, $latencyValue)
    {
        if ($this->configHelper->shouldCaptureMerchantMetrics()) {
        // add delimieter
            $this->addCountMetric($countKey, $countValue);
            $this->addLatencyMetric($latencyKey, $latencyValue);
        
            $count = 0;
            $timeoutMsecs = 10; //number of seconds of timeout
            $availableFile = true;
            $workingFile = fopen($this->metricsFile, "a+");
            while (!flock($workingFile, LOCK_EX | LOCK_NB)) {
                if ($count++ < $timeoutMsecs) {
                    usleep(500);
                } else {
                    $availableFile = false;
                    break;
                }
            }
            if ($availableFile) {
                file_put_contents($this->metricsFile, json_encode($this->Metrics), FILE_APPEND);
                file_put_contents($this->metricsFile, ",", FILE_APPEND);
                flock($workingFile, LOCK_UN);    // release the lock
            }

            fclose($workingFile);
        }
    }

    public function processMetricsThread($countKey, $countValue, $latencyKey, $latencyValue)
    {
        $this->logHelper->addInfoLog($countKey);
        $pool = Pool::create();

        $pool[] = async(function () use ($countKey, $countValue, $latencyKey, $latencyValue) {
            $this->processMetrics($countKey, $countValue, $latencyKey, $latencyValue);
        });
        
        await($pool); // figure out how to manage multiple cases
    }

    /**
     * Regsier a new notification callback.
     *
     *
     * @return void
     */
    public function postMetrics()
    {
        if ($this->configHelper->shouldCaptureMerchantMetrics()) {
            try{
                $output = "";
                $count = 0;
                $timeoutMsecs = 10; //number of seconds of timeout
                $availableFile = true;
                $workingFile = fopen($this->metricsFile, "a+");

                // Is it possible for a task to get stuck? Keeps choosing at the wrong time?
                while (!flock($workingFile, LOCK_EX | LOCK_NB)) {
                    if ($count++ < $timeoutMsecs) {
                        usleep(500);
                    } else {
                        $availableFile = false;
                        break;
                    }
                }
                if ($availableFile) 
                {
                    $raw_file = "[" . rtrim(file_get_contents($this->metricsFile), ",") . "]";
                
                    $output = json_decode($raw_file, true);
                    
                }
                $outputMetrics = ['metrics' => $output];
                $response = $this->guzzleClient->post("v1/merchant/metrics", [
                    'headers' => $this->Headers, 
                    'json' => $outputMetrics,
                ]);

                // Clear File if successfully posted
                if ($response->getStatusCode() == 200) {
                    file_put_contents($this->metricsFile, ""); 
                }
            } catch (\Exception $e) {
                // $this->catchExceptionAndSendError($e, $e->getMessage(), 6009, 422);
            } finally {
                flock($workingFile, LOCK_UN);    // release the lock
                fclose($workingFile);
            }
        }
    }
}
