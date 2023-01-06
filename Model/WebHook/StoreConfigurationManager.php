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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\WebHook;

use Bolt\Boltpay\Api\StoreConfigurationManagerInterface;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Logger\Logger;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\WebHook\StoreConfigurationRequestBuilder;
use Magento\Framework\Exception\LocalizedException;


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
     * @var Logger
     */
    private $logger;

    /**
     * @param ApiHelper $apiHelper
     * @param StoreConfigurationRequestBuilder $storeConfigurationRequestBuilder
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        ApiHelper $apiHelper,
        StoreConfigurationRequestBuilder $storeConfigurationRequestBuilder,
        Config $config,
        Logger $logger
    ) {
        $this->apiHelper = $apiHelper;
        $this->storeConfigurationRequestBuilder = $storeConfigurationRequestBuilder;
        $this->config = $config;
        $this->logger = $logger;
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
                    __(
                        'Error response status during %1 request, status: %2',
                        StoreConfigurationRequestBuilder::API_REQUEST_API_URL,
                        $responseStatus
                        )
                    );
            }

        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }
}
