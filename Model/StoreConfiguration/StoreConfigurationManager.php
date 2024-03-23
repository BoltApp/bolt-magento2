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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\StoreConfiguration;

use Bolt\Boltpay\Api\StoreConfigurationManagerInterface;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Store configuration manager
 */
class StoreConfigurationManager implements StoreConfigurationManagerInterface
{
    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var StoreConfigurationRequestBuilder
     */
    private $storeConfigurationRequestBuilder;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ApiHelper $apiHelper
     * @param StoreConfigurationRequestBuilder $storeConfigurationRequestBuilder
     * @param Config $config
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ApiHelper $apiHelper,
        StoreConfigurationRequestBuilder $storeConfigurationRequestBuilder,
        Config $config,
        Bugsnag $bugsnag
    ) {
        $this->apiHelper = $apiHelper;
        $this->storeConfigurationRequestBuilder = $storeConfigurationRequestBuilder;
        $this->config = $config;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @inheritDoc
     */
    public function requestStoreConfigurationUpdated(string $storeCode): bool
    {
        try {
            $request = $this->storeConfigurationRequestBuilder->getRequest($storeCode);
            $responseStatus = $this->apiHelper->sendRequest($request);
            if ($this->storeConfigurationRequestBuilder->isSuccessfulResponseStatus((int)$responseStatus)) {
                return true;
            } else {
                throw new LocalizedException(
                    __('Error response status during %1 request, status: %2', StoreConfigurationRequestBuilder::API_REQUEST_API_URL, $responseStatus) //@phpstan-ignore-line
                    );
            }

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return false;
        }
    }
}
