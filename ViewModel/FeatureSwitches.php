<?php

namespace Bolt\Boltpay\ViewModel;

use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Feature switches view model
 */
class FeatureSwitches implements ArgumentInterface
{
    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @param Decider $featureSwitches
     */
    public function __construct(Decider $featureSwitches)
    {
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return false|mixed
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->featureSwitches, $name)) {
            return false;
        }
        return call_user_func_array([$this->featureSwitches, $name], $arguments);
    }
}