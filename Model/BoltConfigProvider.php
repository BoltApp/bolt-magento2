<?php

namespace Bolt\Boltpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

/**
 * Class BoltConfigProvider
 * Makes data available from javascript.
 *
 * @package Bolt\Boltpay\Model
 */
class BoltConfigProvider implements ConfigProviderInterface
{
	protected $configHelper;

	/**
	 * BoltConfigProvider constructor.
	 *
	 * @param ConfigHelper $configHelper
	 */
	public function __construct(
		ConfigHelper      $configHelper
	) {
		$this->configHelper = $configHelper;
	}

	public function getConfig() {
		$config = [];
		$config['boltpay_payment']  = $this->configHelper->getPublishableKeyPayment()  != '';
		$config['boltpay_checkout'] = $this->configHelper->getPublishableKeyCheckout() != '';
		return $config;
	}
}
