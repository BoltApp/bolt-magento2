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

namespace Bolt\Boltpay\Helper\FeatureSwitch;

use Bolt\Boltpay\Model\ResourceModel\FeatureSwitch\Collection as FeatureSwitchCollection;

/**
 * Debug class, generating debug data of feature switchers
 */
class Debug
{
    /**
     * @var FeatureSwitchCollection
     */
    private $featureSwitchCollection;

    /**
     * @param FeatureSwitchCollection $featureSwitchCollection
     */
    public function __construct(
        FeatureSwitchCollection $featureSwitchCollection
    ) {
        $this->featureSwitchCollection = $featureSwitchCollection;
    }

    /**
     * Returns full debug feature switches data as array
     *
     * @return array
     */
   public function getDebugFullData(): array
   {
       return $this->getCurrentConfiguration();
   }

    /**
     * Returns current configuration of feature switches
     *
     * @return array
     */
   private function getCurrentConfiguration(): array
   {
       if (!$this->featureSwitchCollection->count()) {
           return [];
       }

       $result = [];
       foreach ($this->featureSwitchCollection as $featureSwitch) {
           $result[] = $featureSwitch->getData();
       }
       return $result;
   }
}
