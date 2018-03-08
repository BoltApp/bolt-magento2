<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Bugsnag\Client as BugsnagClient;
use Bugsnag\Handler as BugsnagHandler;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

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
	 * @param Config  $configHelper
	 *
	 * @codeCoverageIgnore
	 */
    public function __construct(
        Context      $context,
	    ConfigHelper $configHelper
    ) {
        parent::__construct($context);

	    $this->configHelper = $configHelper;

	    $release_stage = $this->configHelper->isSandboxModeSet() ? self::STAGE_DEVELOPMENT : self::STAGE_PRODUCTION;

	    $this->bugsnag = BugsnagClient::make(self::API_KEY);
	    $this->bugsnag->getConfig()->setReleaseStage($release_stage);
	    BugsnagHandler::register($this->bugsnag);
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
}
