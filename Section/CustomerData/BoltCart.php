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
 *
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Section\CustomerData;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

class BoltCart implements SectionSourceInterface
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param CartHelper $cartHelper
     * @param Decider $featureSwitches
     */
    public function __construct(
        CartHelper $cartHelper,
        Decider $featureSwitches,
        ConfigHelper $configHelper
    ) {
        $this->cartHelper = $cartHelper;
        $this->featureSwitches = $featureSwitches;
        $this->configHelper = $configHelper;

    }

    public function getSectionData()
    {
        if (!$this->configHelper->isActive()) {
            return [];
        }
        return (!$this->featureSwitches->isEnabledFetchCartViaApi())
            ? $this->cartHelper->calculateCartAndHints() : [];
    }
}
