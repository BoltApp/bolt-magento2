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

    public function save(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch) {
        $switch->getResource()->save($switch);
        return $switch;
    }

    public function delete(\Bolt\Boltpay\Api\Data\FeatureSwitchInterface $switch) {
        $switch->getResource()->delete($switch);
    }
}