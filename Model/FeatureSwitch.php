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

namespace Bolt\Boltpay\Model;

use Magento\Framework\Model\AbstractModel;
use Bolt\Boltpay\Api\Data\FeatureSwitchInterface;

class FeatureSwitch extends AbstractModel implements \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
{
    const NAME = 'switch_name';
    const VALUE = 'switch_value';
    const DEFAULT_VALUE = 'default_value';
    const ROLLOUT_PERCENTAGE = 'rollout_percentage';

    protected function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\FeatureSwitch::class);
    }

    public function getName()
    {
        return $this->_getData(self::NAME);
    }

    public function setName($name)
    {
        $this->setData(self::NAME, $name);
    }

    public function getValue()
    {
        return $this->_getData(self::VALUE);
    }

    public function setValue($value)
    {
        $this->setData(self::VALUE, $value);
    }

    public function getDefaultValue()
    {
        return $this->_getData(self::DEFAULT_VALUE);
    }

    public function setDefaultValue($defaultValue)
    {
        $this->setData(self::DEFAULT_VALUE, $defaultValue);
    }

    public function getRolloutPercentage()
    {
        return $this->_getData(self::ROLLOUT_PERCENTAGE);
    }

    public function setRolloutPercentage($rolloutPercentage)
    {
        $this->setData(self::ROLLOUT_PERCENTAGE, $rolloutPercentage);
    }
}
