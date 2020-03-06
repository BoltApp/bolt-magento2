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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
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
    public function shouldTrackCheckoutFunnel() {
        return $this->configHelper->shouldTrackCheckoutFunnel();
    }

    /**
     * Get checkout key. Any of the defined publishable keys for use with track.js.
     *
     * @return  string
     */
    public function getCheckoutKey()
    {
        if($this->configHelper->isPaymentOnlyCheckoutEnabled() && $this->_request->getFullActionName() == Config::CHECKOUT_PAGE_ACTION){
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
        return !$this->getCheckoutKey() || !$this->configHelper->getApiKey() || !$this->configHelper->getSigningSecret();
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
}