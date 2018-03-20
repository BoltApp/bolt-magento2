<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\Filesystem\DirectoryList;

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

	/**
	 * @var BugsnagClient
	 */
	protected $bugsnag;

	/**
	 * @var ConfigHelper
	 */
	protected $configHelper;

	/**
	 * @param Context $context
	 * @param Config $configHelper
	 *
	 * @param DirectoryList $directoryList
	 *
	 * @codeCoverageIgnore
	 */
    public function __construct(
        Context       $context,
	    ConfigHelper  $configHelper,
	    DirectoryList $directoryList
    ) {
        parent::__construct($context);

        //////////////////////////////////////////
        // Uncomment for composerless installation.
	    // Make sure libraries are in place.
	    //////////////////////////////////////////
	    /*if (!class_exists('\GuzzleHttp\Client')) {
		    require_once $directoryList->getPath('lib_internal') . '/Bolt/guzzle/autoloader.php';
	    }

	    if (!class_exists('\Bugsnag\Client')) {
		    require_once $directoryList->getPath('lib_internal') . '/Bolt/bugsnag/autoloader.php';
	    }*/
	    //////////////////////////////////////////

	    $this->configHelper = $configHelper;

	    $release_stage = $this->configHelper->isSandboxModeSet() ? self::STAGE_DEVELOPMENT : self::STAGE_PRODUCTION;

	    $this->bugsnag = \Bugsnag\Client::make(self::API_KEY);
	    $this->bugsnag->getConfig()->setReleaseStage($release_stage);
	    \Bugsnag\Handler::register($this->bugsnag);

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
}
