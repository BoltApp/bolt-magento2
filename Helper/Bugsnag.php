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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Boltpay Bugsnag wrapper helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Bugsnag extends AbstractHelper
{
    const API_KEY           = '888766c6cfe49858afc36b3a2a2c6548';
    const STAGE_DEVELOPMENT = 'development';
    const STAGE_PRODUCTION  = 'production';
    const STAGE_TEST        = 'test';

    /**
     * @var BugsnagClient
     */
    private $bugsnag;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /* @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @param Context $context
     * @param Config $configHelper
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        DirectoryList $directoryList,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;

        //////////////////////////////////////////
        // Composerless installation.
        // Make sure libraries are in place:
        // lib/internal/Bolt/bugsnag
        // lib/internal/Bolt/guzzle
        //////////////////////////////////////////
        if (!class_exists('\GuzzleHttp\Client')) {
            // @codeCoverageIgnoreStart
            require_once $directoryList->getPath('lib_internal') . '/Bolt/guzzle/autoloader.php';
            // @codeCoverageIgnoreEnd
        }

        if (!class_exists('\Bugsnag\Client')) {
            // @codeCoverageIgnoreStart
            require_once $directoryList->getPath('lib_internal') . '/Bolt/bugsnag/autoloader.php';
            // @codeCoverageIgnoreEnd
        }
        //////////////////////////////////////////

        $this->configHelper = $configHelper;

        $this->bugsnag = \Bugsnag\Client::make(self::API_KEY);
        $this->bugsnag->getConfig()->setNotifyReleaseStages([self::STAGE_DEVELOPMENT, self::STAGE_PRODUCTION]);
        $this->bugsnag->getConfig()->setAppVersion($this->configHelper->getModuleVersion());

        if ($this->configHelper->isTestEnvSet()) {
            $this->bugsnag->getConfig()->setReleaseStage(self::STAGE_TEST);
        } else {
            $this->bugsnag->getConfig()->setReleaseStage($this->configHelper->isSandboxModeSet() ? self::STAGE_DEVELOPMENT : self::STAGE_PRODUCTION);
        }

        $this->addCommonMetaData();

        ////////////////////////////////////////////////////////////////////////
        // Reporting unhandled exceptions. This option is turned off by default.
        // All Bolt plugin errors are handled properly.
        ////////////////////////////////////////////////////////////////////////
        // \Bugsnag\Handler::register($this->bugsnag);
        ////////////////////////////////////////////////////////////////////////
    }

    /**
     * Notify Bugsnag of a non-fatal/handled throwable.
     *
     * @param \Throwable    $throwable the throwable to notify Bugsnag about
     * @param callable|null $callback  the customization callback
     *
     * @return void
     */
    public function notifyException($throwable, callable $callback = null)
    {
        $this->bugsnag->notifyException($throwable, $callback);
    }

    /**
     * Notify Bugsnag of a non-fatal/handled error.
     *
     * @param string        $name     the name of the error, a short (1 word) string
     * @param string        $message  the error message
     * @param callable|null $callback the customization callback
     *
     * @return void
     */
    public function notifyError($name, $message, callable $callback = null)
    {
        $this->bugsnag->notifyError($name, $message, $callback);
    }

    /**
     * Regsier a new notification callback.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function registerCallback(callable $callback)
    {
        $this->bugsnag->registerCallback($callback);
    }

    /**
     * Add metadata to every bugsnag log
     */
    private function addCommonMetaData()
    {
        $this->bugsnag->registerCallback(function ($report) {
            /** @var \Bugsnag\Report $report */
            $report->addMetaData([
                'META DATA' => [
                    'store_url' => $this->storeManager->getStore()->getBaseUrl(
                        \Magento\Framework\UrlInterface::URL_TYPE_WEB
                    ),
                    'composer_version' => $this->configHelper->getComposerVersion()
                ]
            ]);
        });
    }
}
