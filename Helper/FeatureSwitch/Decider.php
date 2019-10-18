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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper\FeatureSwitch;

use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Model\FeatureSwitchFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Framework\App\State;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Decider extends AbstractHelper {
    /**
     * @var Manager
     */
    private $_manager;

    /** @var State */
    private $_state;

    /** @var CoreSession */
    private $_session;

    /**
     * @var FeatureSwitchRepository
     */
    private $_fsRepo;

    /**
     * @var FeatureSwitchFactory
     */
    private $_fsFactory;

    /**
     * @param Context              $context
     * @param CoreSession          $coreSession
     * @param State                $state
     * @param Manager              $manager
     * @param FeatureSwitchFactory $fsFactory
     * @param FeatureSwitchRepository $fsRepo
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        CoreSession $coreSession,
        State $state,
        Manager $manager,
        FeatureSwitchRepository $fsRepo,
        FeatureSwitchFactory $fsFactory
    ) {
        parent::__construct($context);
        $this->_session = $coreSession;
        $this->_state = $state;
        $this->_manager = $manager;
        $this->_fsRepo = $fsRepo;
        $this->_fsFactory = $fsFactory;
    }

    /**
     * This method returns if a feature switch is enabled for a user.
     * The way this is computed is as follows:
     * - Get feature switch id
     * - Set if unset.
     * - Add switch name as salt to ID and find md5 hash
     * - Get first 6 digits of MD5 and divide by 0xffffff. Should be between 0 and 1.
     * - Multiply previous value by 100
     *   and compare with rolloutPercentage to decide if in bucket.
     *
     * @param string $switchName
     * @param int $rolloutPercentage
     * @return bool
     */
    private function _isInBucket(string $switchName, int $rolloutPercentage) {
        $this->_session->start();
        $boltFeatureSwitchId = $this->_session->getBoltFeatureSwitchId();
        if (!$boltFeatureSwitchId) {
            $boltFeatureSwitchId = uniqid("BFS", true);
            $this->_session->setBoltFeatureSwitchId($boltFeatureSwitchId);
        }
        $saltedString = $boltFeatureSwitchId . "-" . $switchName;
        $hash = md5($saltedString);
        $hexStr = substr($hash, 0, 6);
        $decEquivalent = hexdec($hexStr);

        $hexMax = hexdec("0xffffff");
        $position = $decEquivalent / (float) $hexMax * 100;
        return $position < $rolloutPercentage;
    }

    private function _switchFromConst($switchDef) {
        $switch = $this->_fsFactory->create();
        $switch->setName($switchDef[Definitions::NAME_KEY]);
        $switch->setValue($switchDef[Definitions::VAL_KEY]);
        $switch->setDefaultValue($switchDef[Definitions::DEFAULT_VAL_KEY]);
        $switch->setRolloutPercentage($switchDef[Definitions::ROLLOUT_KEY]);
        return $switch;
    }

    /**
     * This returns if the switch is enabled.
     *
     * @param string $switchName name of the switch
     *
     * @throws LocalizedException
     * @return bool
     */
    public function isSwitchEnabled($switchName) {
        $defaultDef = isset(Definitions::DEFAULT_SWITCH_VALUES[$switchName]) ?
            Definitions::DEFAULT_SWITCH_VALUES[$switchName] : null;
        if (!$defaultDef) {
            throw new LocalizedException(__("Unknown feature switch"));
        }
        $switch = null;
        try {
            $switch = $this->_fsRepo->getByName($switchName);
        } catch (NoSuchEntityException $e) {
            // Switch is not in DB. Fall back to defaults.
            $switch = $this->_switchFromConst($defaultDef);
        }

        if (!$switch) {
            // Something is really wrong, But we dont want to fail
            // if this sort of weird case occurs.
            return false;
        }

        if ($switch->getRolloutPercentage() == 0) {
            return $switch->getDefaultValue();
        } else if ($switch->getRolloutPercentage() == 100) {
            return $switch->getValue();
        } else {
            $isInBucket = $this
                ->_isInBucket($switchName, $switch->getRolloutPercentage());
            return $isInBucket ? $switch->getValue() : $switch->getDefaultValue();
        }
    }

    /***************************************************
     * Switch Helpers below
     ***************************************************/

    public function isSampleSwitchEnabled() {
        return $this->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);
    }
}