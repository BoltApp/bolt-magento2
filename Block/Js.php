<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Api;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Js Block. The block class used in replace.phtml and track.phtml blocks.
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class Js extends Template
{
    /**
     * @var Api
     */
    protected $_apiHelper;

    /**
     * @var Config
     */
    protected $_configHelper;


    /**
     * @var HttpRequest
     */
    protected $httpRequest;

	/**
	 * @param Context $context
	 * @param Api $apiHelper
	 * @param Config $configHelper
	 * @param HttpRequest $httpRequest
	 * @param array $data
	 */
    public function __construct(
        Context     $context,
        Api         $apiHelper,
        Config      $configHelper,
        HttpRequest $httpRequest,
        array       $data = []
    ) {
        parent::__construct($context, $data);
        $this->_apiHelper    = $apiHelper;
        $this->_configHelper = $configHelper;
        $this->httpRequest   = $httpRequest;
    }

    /**
     * Get track js url
     *
     * @return  string
     */
    public function getTrackJsUrl()
    {
        //Get cdn url
        $cdnUrl = $this->_apiHelper->getCdnUrl();
	    return $cdnUrl.'/track.js';
    }

    /**
     * Get connect js url
     *
     * @return  string
     */
    public function getConnectJsUrl()
    {
        //Get cdn url
	    $cdnUrl = $this->_apiHelper->getCdnUrl();
	    return $cdnUrl.'/connect.js';
    }

    /**
     * Get checkout key. Any of the defined publishable keys for use with track.js.
     *
     * @return  string
     */
    public function getCheckoutKey()
    {
	    return $this->_configHelper->getAnyPublishableKey();
    }

	/**
	 * Get Replace Button Selectors.
	 *
	 * @return string
	 */
	function getReplaceSelectors() {
		return array_filter(explode(',', preg_replace('/\s+/', ' ', trim($this->_configHelper->getReplaceSelectors()))));
	}

	/**
	 * Get Javascript page settings.
	 * @return string
	 */
	function getSettings() {

		return json_encode([
			'connect_url'              => $this->getConnectJsUrl(),
			'publishable_key_payment'  => $this->_configHelper->getPublishableKeyPayment(),
			'publishable_key_checkout' => $this->_configHelper->getPublishableKeyCheckout(),
			'create_order_url'         => $this->getUrl(Config::CREATE_ORDER_ACTION),
			'save_order_url'           => $this->getUrl(Config::SAVE_ORDER_ACTION),
			'selectors'                => $this->getReplaceSelectors(),
		]);
	}

	/**
	 * Get Bolt Payment module active state.
	 * @return bool
	 */
	function isActive() {
		return $this->_configHelper->isActive();
	}
}
