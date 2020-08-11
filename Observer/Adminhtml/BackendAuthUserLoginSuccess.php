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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer\Adminhtml;


class BackendAuthUserLoginSuccess implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Bolt\Boltpay\Model\Updater
     */
    private $updater;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\AdminNotification\Model\ResourceModel\Inbox\CollectionFactory
     */
    private $adminNotificationCollectionFactory;

    /**
     * @var \Magento\AdminNotification\Model\Inbox
     */
    private $adminNotificationInbox;

    public function __construct(
        \Bolt\Boltpay\Model\Updater $updater,
        \Magento\Framework\Module\Manager $moduleManager,
        \Bolt\Boltpay\Helper\Config $config,
        \Magento\AdminNotification\Model\Inbox $adminNotificationInbox,
        \Magento\AdminNotification\Model\ResourceModel\Inbox\CollectionFactory $adminNotificationCollectionFactory
    ) {
        $this->updater = $updater;
        $this->moduleManager = $moduleManager;
        $this->config = $config;
        $this->adminNotificationCollectionFactory = $adminNotificationCollectionFactory;
        $this->adminNotificationInbox = $adminNotificationInbox;
    }

    /**
     * @inheritDoc
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->moduleManager->isEnabled('Magento_AdminNotification')
            || !$this->updater->getIsUpdateAvailable()) {
            return;
        }
        $latestVersion = $this->updater->getVersion();
        $updateSeverity = $this->updater->getSeverity();
        $updateTitle = $this->updater->getUpdateTitle();
        $description = __(
            'Installed version: %1. Latest version: %2',
            $this->config->getModuleVersion(),
            $latestVersion
        );
        $collection = $this->adminNotificationCollectionFactory->create()
            ->addFieldToFilter('title', $updateTitle)
            ->addFieldToFilter('description', $description);
        if ($collection->getSize() == 0 && $this->adminNotificationInbox->getSeverities($updateSeverity)) {
            $this->adminNotificationInbox->add(
                $updateSeverity,
                $updateTitle,
                $description,
                '',
                false
            );
        }
    }
}
