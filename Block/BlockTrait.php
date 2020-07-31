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
namespace Bolt\Boltpay\Block;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

trait BlockTrait
{
    /**
     * @var Config
     */
    protected $configHelper;


    /** @var Decider */
    protected $featureSwitches;

    /**
     * @return mixed
     */
    public function shouldTrackCheckoutFunnel()
    {
        return $this->configHelper->shouldTrackCheckoutFunnel();
    }

    /**
     * Get checkout key. Any of the defined publishable keys for use with track.js.
     *
     * @return  string
     */
    public function getCheckoutKey()
    {
        if ($this->configHelper->isPaymentOnlyCheckoutEnabled()
            && $this->_request->getFullActionName() == Config::CHECKOUT_PAGE_ACTION) {
            return $this->configHelper->getPublishableKeyPayment();
        }

        return $this->configHelper->getPublishableKeyCheckout();
    }

    /**
     * Return true if publishable key or API key is empty
     * @return bool
     */
    public function isKeyMissing()
    {
        return !$this->getCheckoutKey()
            || !$this->configHelper->getApiKey()
            || !$this->configHelper->getSigningSecret();
    }

    /**
     * If we have multi-website, we need current store_id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    /**
     * Get Bolt Payment module active state.
     * @return bool
     */
    public function isEnabled()
    {
        $storeId = $this->getStoreId();
        return $this->configHelper->isActive($storeId);
    }

    /**
     * Get blacklisted pages, stored in "pageFilters.blacklist" additional configuration
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @return array
     */
    protected function getPageBlacklist()
    {
        return $this->configHelper->getPageBlacklist();
    }

    /**
     * Check if Bolt checkout is restricted on the current loading page.
     * Takes into account Minicart support and whitelisted / blacklisted pages configuration
     * as well as the IP restriction.
     * "Full Action Name", <router_controller_action>, is used to identify the page.
     *
     * @return bool
     */
    private function isPageRestricted()
    {
        $currentPage = $this->getRequest()->getFullActionName();

        // Check if the page is blacklisted
        if (in_array($currentPage, $this->getPageBlacklist())) {
            return true;
        }

        // If IP whitelist is defined, the Bolt checkout functionality
        // must be limited to the non cached pages, shopping cart and checkout (internal or 3rd party).
        if (!$this->configHelper->getIPWhitelistArray()) {
            return false;
        }
        return !in_array($currentPage, $this->getPageWhitelist());
    }

    /**
     * Get whitelisted pages, the default, non cached, shopping cart and checkout pages,
     * and the pages stored in "pageFilters.whitelist" additional configuration,
     * as an array of "Full Action Name" identifiers, [<router_controller_action>]
     *
     * @return array
     */
    protected function getPageWhitelist()
    {
        $values =  $this->configHelper->getPageWhitelist();
        return array_unique(array_merge(Config::$defaultPageWhitelist, $values));
    }

    /**
     * Check if the client IP is restricted -
     * there is an IP whitelist and the client IP is not on the list.
     *
     * @return bool
     */
    protected function isIPRestricted()
    {
        return $this->configHelper->isIPRestricted();
    }

    /**
     * Return true if we need to disable bolt scripts and button
     * Checks whether the module is active,
     * the page is Bolt checkout restricted and if there is an IP restriction.
     *
     * @return bool
     */
    public function shouldDisableBoltCheckout()
    {
        if (!$this->featureSwitches->isBoltEnabled()) {
            return true;
        }
        return !$this->isEnabled() || $this->isPageRestricted() || $this->isIPRestricted() || $this->isKeyMissing();
    }

    /**
     * Return checkout cdn url for use in checkout button v2
     */
    public function getCheckoutCdnUrl()
    {
        return $this->configHelper->getCdnUrl();
    }

    /**
     * Return if instant bolt checkout button should be displayed
     */
    public function isInstantCheckoutButton()
    {
        return $this->featureSwitches->isInstantCheckoutButton();
    }
}
