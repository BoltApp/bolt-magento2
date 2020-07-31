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

namespace Bolt\Boltpay\Api\Data;

interface FeatureSwitchInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return void
     */
    public function setName($name);

    /**
     * @return boolean
     */
    public function getValue();

    /**
     * @param boolean $value
     * @return void
     */
    public function setValue($value);

    /**
     * @return boolean
     */
    public function getDefaultValue();

    /**
     * @param boolean $defaultValue
     * @return void
     */
    public function setDefaultValue($defaultValue);

    /**
     * @return int
     */
    public function getRolloutPercentage();

    /**
     * @param int $rolloutPercentage
     * @return void
     */
    public function setRolloutPercentage($rolloutPercentage);
}
