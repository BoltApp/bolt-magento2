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
namespace Bolt\Boltpay\Plugin\Magento\Config\Model;

use Bolt\Boltpay\Api\StoreConfigurationManagerInterface;
use Bolt\Boltpay\Helper\Config as BoltConfig;
use Magento\Config\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\App\ScopeInterface as AppScopeInterface;

/**
 * Send bolt request after configuration save
 */
class ConfigPlugin
{
    /**
     * @var StoreConfigurationManagerInterface
     */
    private $storeConfigurationManager;

    /**
     * @var BoltConfig
     */
    private $boltConfig;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @param StoreConfigurationManagerInterface $storeConfigurationManager
     * @param BoltConfig $boltConfig
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        StoreConfigurationManagerInterface $storeConfigurationManager,
        BoltConfig $boltConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->storeConfigurationManager = $storeConfigurationManager;
        $this->boltConfig = $boltConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Send request to bolt after magento configuration update
     *
     * @param Config $subject
     * @param $result
     * @return Config
     */
    public function afterSave(
        Config $subject,
        Config $result
    ): Config {

        if ($result->getScope() == AppScopeInterface::SCOPE_DEFAULT) {
            $websites = $this->websiteRepository->getList();
            foreach ($websites as $website) {
                if ($website->getWebsiteId() == 0) {
                    continue;
                }
                if ($this->boltConfig->getIsSystemConfigurationUpdateRequestEnabled($website->getId())
                ) {
                    foreach ($website->getStores() as $store) {
                        $this->storeConfigurationManager->requestStoreConfigurationUpdated($store->getCode());
                    }
                }
            }
        }

        if ($result->getScope() == ScopeInterface::SCOPE_WEBSITES &&
            $this->boltConfig->getIsSystemConfigurationUpdateRequestEnabled($result->getWebsite())
        ) {
            $website = $this->websiteRepository->getById((int)$result->getWebsite());
            foreach ($website->getStores() as $store) {
                $this->storeConfigurationManager->requestStoreConfigurationUpdated($store->getCode());
            }
        }

        if ($result->getScope() == ScopeInterface::SCOPE_STORES) {
            $this->storeConfigurationManager->requestStoreConfigurationUpdated($result->getScopeCode());
        }

        return $result;
    }
}
