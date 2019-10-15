<?php


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