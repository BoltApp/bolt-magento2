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
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Logger\Logger;

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
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param StoreConfigurationManagerInterface $storeConfigurationManager
     * @param BoltConfig $boltConfig
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param StoreRepositoryInterface $storeRepository
     * @param Decider $featureSwitches
     * @param Logger $logger
     */
    public function __construct(
        StoreConfigurationManagerInterface $storeConfigurationManager,
        BoltConfig $boltConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        Decider $featureSwitches,
        Logger $logger
    ) {
        $this->storeConfigurationManager = $storeConfigurationManager;
        $this->boltConfig = $boltConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->featureSwitches = $featureSwitches;
        $this->logger = $logger;
    }

    /**
     * Send request to bolt after magento configuration update
     *
     * @param Config $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterSave(
        Config $subject,
        $result
    ) {
        if (!$this->featureSwitches->isStoreConfigurationWebhookEnabled()) {
            return $result;
        }
        try {
            if ($subject->getScope() == AppScopeInterface::SCOPE_DEFAULT) {
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

            if ($subject->getScope() == ScopeInterface::SCOPE_WEBSITES &&
                $this->boltConfig->getIsSystemConfigurationUpdateRequestEnabled($subject->getWebsite())
            ) {
                $website = $this->websiteRepository->getById((int)$subject->getWebsite());
                foreach ($website->getStores() as $store) {
                    $this->storeConfigurationManager->requestStoreConfigurationUpdated($store->getCode());
                }
            }

            if ($subject->getScope() == ScopeInterface::SCOPE_STORES &&
                $this->boltConfig->getIsSystemConfigurationUpdateRequestEnabled($subject->getScopeId())
            ) {
                $this->storeConfigurationManager->requestStoreConfigurationUpdated($subject->getScopeCode());
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $result;
    }
}
