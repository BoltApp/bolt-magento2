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

declare(strict_types=1);

namespace Bolt\Boltpay\Model\VersionNotifier;

class VersionValidator
{
    private const PLUGIN_NAME = 'Bolt_Boltpay';

    private \Bolt\Boltpay\Service\GitApiService $gitApiService;
    private \Magento\Framework\Module\ResourceInterface $moduleResource;
    private \Bolt\Boltpay\Logger\Logger $logger;
    private \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotification $versionNotifier;
    private \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder;
    private \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotificationRepository $versionNotificationRepository;

    public function __construct(
        \Bolt\Boltpay\Service\GitApiService $gitApiService,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Bolt\Boltpay\Logger\Logger $logger,
        \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotification $versionNotifier,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotificationRepository $versionNotificationRepository
    ) {
        $this->gitApiService = $gitApiService;
        $this->moduleResource = $moduleResource;
        $this->logger = $logger;
        $this->versionNotifier = $versionNotifier;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->versionNotificationRepository = $versionNotificationRepository;
    }

    public function checkVersions(): void
    {
        if ($this->getLatestVersion() && $this->getCurrentVersion() !== $this->getLatestVersion()) {
            $this->cleanOldNotificationData();
            $this->versionNotificationRepository->save($this->versionNotifier);
        } elseif ($this->getCurrentVersion() == $this->getLatestVersion()) {
            $this->cleanOldNotificationData();
        }
    }

    private function getLatestVersion()
    {
        $response = $this->gitApiService->getLatestRelease();

        if ($response['status'] !== 200 || !isset($response['response_body']['name'])) {
            $this->logger->error($response['response_body']);
            return false;
        }

        $this->versionNotifier->setLetestVersion($response['response_body']['name']);
        $this->versionNotifier->setDescription($response['response_body']['body']);

        return $this->versionNotifier->getLetestVersion();
    }

    private function getCurrentVersion(): string
    {
        return $this->moduleResource->getDbVersion(self::PLUGIN_NAME);
    }

    private function cleanOldNotificationData(): void
    {
        $searchResult = $this->versionNotificationRepository->getList($this->searchCriteriaBuilder->create());
        foreach ($searchResult->getItems() as $item) {
            $this->versionNotificationRepository->delete($item);
        }
    }
}
