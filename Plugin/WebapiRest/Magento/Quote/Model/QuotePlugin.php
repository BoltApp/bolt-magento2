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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Quote\Model;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\App\State;

/**
 * Plugin for {@see \Magento\Quote\Model\Quote}
 */
class QuotePlugin
{
    /**
     * @var \Bolt\Boltpay\Helper\FeatureSwitch\Decider
     */
    private $featureSwitches;

    /**
     * @var bool
     */
    private $isPreventSettingBoltIpsAsCustomerIpOnQuote;

    /**
     * @var State
     */
    private $appState;

    /**
     * @param Decider|null $featureSwitches
     * @param State $appState
     */
    public function __construct(
        State $appState,
        ?Decider $featureSwitches = null
    ) {
        $this->featureSwitches = $featureSwitches ?? \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Bolt\Boltpay\Helper\FeatureSwitch\Decider::class);
        $this->isPreventSettingBoltIpsAsCustomerIpOnQuote = $this->featureSwitches->isPreventSettingBoltIpsAsCustomerIpOnQuote();
        $this->appState = $appState;
    }

    /**
     * Prevent IP from being set when requests are coming from Bolt, except initially
     *
     * @param \Magento\Quote\Model\Quote $subject intercepted quote object
     * @param array|string               $key
     * @param mixed                      $value
     *
     * @return array|void
     */
    public function beforeSetData(\Magento\Quote\Model\Quote $subject, $key, $value = null)
    {
        if ($this->isPreventSettingBoltIpsAsCustomerIpOnQuote
            && $key === 'remote_ip' &&
            ($this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_WEBAPI_REST || \Bolt\Boltpay\Helper\Hook::$fromBolt)
        ) {
            return [$key, ($subject->getData('remote_ip') ?: $subject->getOrigData('remote_ip')) ?: $value];
        }
    }
}
