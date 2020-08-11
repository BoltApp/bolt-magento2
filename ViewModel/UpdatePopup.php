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

namespace Bolt\Boltpay\ViewModel;

use Magento\Framework\Notification\MessageInterface;

/**
 * Update popup view model
 *
 * @package Bolt\Boltpay\ViewModel
 */
class UpdatePopup implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    const BOLT_POPUP_SHOWN = 'bolt_popup_shown';

    /**
     * @var \Bolt\Boltpay\Model\Updater
     */
    private $updater;

    /**
     * @var \Magento\Backend\Model\Auth\Session\Proxy
     */
    private $session;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Framework\App\DocRootLocator
     */
    private $docRootLocator;

    /**
     * @var \Magento\Backend\Model\Url
     */
    private $url;

    /**
     * UpdatePopup constructor.
     * @param \Bolt\Boltpay\Model\Updater           $updater
     * @param \Magento\Backend\Model\Auth\Session   $session
     * @param \Magento\Framework\Module\Manager     $moduleManager
     * @param \Magento\Framework\App\DocRootLocator $docRootLocator
     * @param \Magento\Backend\Model\Url            $url
     */
    public function __construct(
        \Bolt\Boltpay\Model\Updater $updater,
        \Magento\Backend\Model\Auth\Session $session,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\App\DocRootLocator $docRootLocator,
        \Magento\Backend\Model\Url $url
    ) {
        $this->updater = $updater;
        $this->session = $session;
        $this->moduleManager = $moduleManager;
        $this->docRootLocator = $docRootLocator;
        $this->url = $url;
    }

    /**
     * Determines if update popup should be rendered or not
     * Depends on:
     * 1. Magento_AdminNotification module being enabled
     * 2. An update for Boltpay being available
     * 3. Popup not already displayed in this admin session
     * 4. Notification allowed by ACL for current administrator
     * 5. Notifications are not disabled for non critical updates (only for minor updates)
     * 6. Popup not displayed previously for the same version (only for minor updates)
     *
     * @return bool true if all conditions are met, otherwise false
     */
    public function shouldDisplay()
    {
        $shouldDisplay = $this->moduleManager->isEnabled('Magento_AdminNotification')
            && !$this->session->getData(self::BOLT_POPUP_SHOWN)
            && $this->session->isAllowed('Magento_AdminNotification::show_toolbar')
            && $this->updater->getIsUpdateAvailable();
        if ($shouldDisplay && $this->updater->getSeverity() == MessageInterface::SEVERITY_NOTICE) {
            $adminUser = $this->session->getUser();
            /** @var array $adminExtra */
            $adminExtra = $adminUser->getExtra();
            $latestVersion = $this->updater->getVersion();
            if (!is_array($adminExtra)
                || !key_exists('bolt_minor_update_popups_shown', $adminExtra)
                || !is_array($adminExtra['bolt_minor_update_popups_shown'])) {
                $adminExtra['bolt_minor_update_popups_shown'] = [];
            }

            if (in_array($latestVersion, $adminExtra['bolt_minor_update_popups_shown'])) {
                return false;
            }

            $adminExtra['bolt_minor_update_popups_shown'][] = $latestVersion;
            $adminUser->saveExtra($adminExtra);
        }
        if ($shouldDisplay) {
            $this->session->setData(self::BOLT_POPUP_SHOWN, true);
        }
        return $shouldDisplay;
    }

    /**
     * Gets Setup Wizard URL
     *
     * @return string|void setup wizard url if available, otherwise null
     */
    public function getSetupWizardUrl()
    {
        if ($this->session->isAllowed('Magento_Backend::setup_wizard')
            && !$this->docRootLocator->isPub()/** @see \Magento\Backend\Model\Setup\MenuBuilder */) {
            return $this->url->getUrl('adminhtml/backendapp/redirect/app/setup');
        }
    }

    /**
     * Gets the link to the archive of the provided Bolt plugin version
     *
     * @param string $version for which to retrieve the release download link
     *
     * @return string The Github link to the provided version zip file
     */
    public function getReleaseDownloadLink($version)
    {
        return sprintf(
            'https://github.com/BoltApp/bolt-magento2/archive/%s.zip',
            $version
        );
    }

    /**
     * Gets updater singleton model instance, for usage in templates
     *
     * @return \Bolt\Boltpay\Model\Updater
     */
    public function getUpdater()
    {
        return $this->updater;
    }
}
