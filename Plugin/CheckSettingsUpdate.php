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

namespace Bolt\Boltpay\Plugin;

use Magento\Config\Model\Config as MagentoConfig;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager as FeatureSwitchManager;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class ClearQuote
 *
 * @package Bolt\Boltpay\Plugin
 */
class CheckSettingsUpdate
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /*
     * @var FeatureSwitchManager
     */
    protected $fsManager;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * $string
     */
    private $oldApiKey;

    /**
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param FeatureSwitchManager $fsManager
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        FeatureSwitchManager $fsManager,
        Bugsnag $bugsnag
    ) {
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
        $this->fsManager = $fsManager;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @param CheckoutSession $subject
     * @return null Return null because method Save have no arguments
     */
    public function beforeSave(MagentoConfig $subject)
    {
        $this->oldApiKey = $this->configHelper->getApiKey();
        return null;
    }

    /**
     * @param CheckoutSession $subject
     * @return CheckoutSession
     */
    public function afterSave(MagentoConfig $subject, $result)
    {
        $apiKey = $this->configHelper->getApiKey();
        if ($apiKey && $apiKey != $this->oldApiKey) {
            try {
                $this->fsManager->updateSwitchesFromBolt();
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }
        return $result;
    }
}
