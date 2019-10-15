<?php


namespace Bolt\Boltpay\Api;

use Bolt\Boltpay\Api\Data\FeatureSwitchInterface;

interface FeatureSwitchRepositoryInterface {
    /**
     * @param string $name
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByName($name);

    /**
     * @param \Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     */
    public function save(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch);

    /**
     * @param \Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch
     * @return void
     */
    public function delete(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch);
}