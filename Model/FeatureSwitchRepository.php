<?php


namespace Bolt\Boltpay\Model;

use Magento\Framework\Exception\NoSuchEntityException;

class FeatureSwitchRepository implements \Bolt\Boltpay\Api\FeatureSwitchRepositoryInterface {
    /**
     * @var FeatureSwitchFactory
     */
    private $featureSwitchFactory;

    public function __construct(
        FeatureSwitchFactory $featureSwitchFactory
    ) {
        $this->featureSwitchFactory = $featureSwitchFactory;
    }

    public function getByName($name) {
        $switch = $this->featureSwitchFactory->create();
        $switch->getResource()->load($switch, $name, FeatureSwitch::NAME);
        if (! $switch->getName()) {
            throw new NoSuchEntityException(__('Unable to find switch with name "%1"', $name));
        }
        return $switch;
    }

    /**
     * Either creates a new switch or updates an existing switch.
     *
     * @param string $name
     * @param boolean $value
     * @param boolean $defaultValue
     * @param int $rolloutPercentage
     * @return \Bolt\Boltpay\Api\Data\FeatureSwitchInterface
     */
    public function upsertByName($name, $value, $defaultValue, $rolloutPercentage) {
        $switch = null;
        try {
            $switch = $this->getByName($name);
        } catch(NoSuchEntityException $e) {
            // If the switch is not found we create it.
            $switch = $this->featureSwitchFactory->create();
            $switch->setName($name);
        }
        $switch->setValue($value);
        $switch->setDefaultValue($defaultValue);
        $switch->setRolloutPercentage($rolloutPercentage);
        return $this->save($switch);
    }

    public function save(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch) {
        $switch->getResource()->save($switch);
        return $switch;
    }

    public function delete(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch) {
        $switch->getResource()->delete($switch);
    }
}