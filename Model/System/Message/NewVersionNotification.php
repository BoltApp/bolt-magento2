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

namespace Bolt\Boltpay\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;

class NewVersionNotification implements MessageInterface
{
    const MESSAGE_IDENTITY = 'pluginVersionNotifier';

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotificationRepository
     */
    private $versionNotificationRepository;

    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    private $config;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Bolt\Boltpay\Model\VersionNotifier\PluginVersionNotificationRepository $versionNotificationRepository,
        \Bolt\Boltpay\Helper\Config $config
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->versionNotificationRepository = $versionNotificationRepository;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * @return bool
     */
    public function isDisplayed()
    {
        if ($this->config->getNewPluginVersionNotificationEnabled()) {
            return true;
        }
        return false;
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getText()
    {
        $newVersionInfo = $this->getNewVersionInfo();
        if ($newVersionInfo) {
            $description = str_replace("\n", '<br>', $newVersionInfo->getDescription());
            return __(sprintf("New %s version of Bolt module is available! <br>%s", $newVersionInfo->getLatestVersion(), $description));
        }
        return '';
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    private function getNewVersionInfo()
    {
        $searchResult = $this->versionNotificationRepository->getList($this->searchCriteriaBuilder->create());
        if ($searchResult->getTotalCount()) {
            foreach ($searchResult->getItems() as $item) {
                return $item;
            }
        }

        return false;
    }

}
