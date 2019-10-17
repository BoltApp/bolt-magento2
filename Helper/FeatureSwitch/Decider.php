<?php


namespace Bolt\Boltpay\Helper\FeatureSwitch;


use Bolt\Boltpay\Helper\GraphQL\Client as GQL;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Decider extends AbstractHelper {
    /**
     * @var Manager
     */
    private $_manager;

    /**
     * @param Context $context
     * @param Manager $manager
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        Manager $manager
    ) {
        parent::__construct($context);
        $this->_manager = $manager;
    }

    public function isSampleSwitchEnabled() {
        return $this->_manager->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);
    }
}