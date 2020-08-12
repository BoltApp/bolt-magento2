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

namespace Bolt\Boltpay\Helper\FeatureSwitch;

use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Model\FeatureSwitchFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Framework\App\State;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Decider extends AbstractHelper
{
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
    private function _isInBucket(string $switchName, int $rolloutPercentage)
    {
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

    private function _switchFromConst($switchDef)
    {
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
    public function isSwitchEnabled($switchName)
    {
        $defaultDef = @Definitions::DEFAULT_SWITCH_VALUES[$switchName];
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
            $isSwitchEnabled = $switch->getDefaultValue();
        } else if ($switch->getRolloutPercentage() == 100) {
            $isSwitchEnabled = $switch->getValue();
        } else {
            $isInBucket = $this
                ->_isInBucket($switchName, $switch->getRolloutPercentage());
            $isSwitchEnabled =$isInBucket ? $switch->getValue() : $switch->getDefaultValue();
        }

        return (bool) $isSwitchEnabled;
    }

    /***************************************************
     * Switch Helpers below
     ***************************************************/

    /**
     * Checks whether the sample feature switch is enabled
     */
    public function isSampleSwitchEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_SAMPLE_SWITCH_NAME);
    }

    /**
     * Checks whether the feature switch for enabling/disabling bolt is enabled
     */
    public function isBoltEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_BOLT_ENABLED);
    }

    /**
     * Checks whether the feature switch for logging missing quote failed hook is enabled
     */
    public function isLogMissingQuoteFailedHooksEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_LOG_MISSING_QUOTE_FAILED_HOOKS);
    }

    public function isCreatingCreditMemoFromWebHookEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED);
    }

    public function isAdminReorderForLoggedInCustomerFeatureEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER);
    }

    /**
     * Checks whether the feature switch for saving/sending tracking data is enabled
     */
    public function isTrackShipmentEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_TRACK_SHIPMENT);
    }

    /**
     * Checks whether the feature switch for ingesting Non-Bolt order information is enabled
     */
    public function isNonBoltTrackingEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_TRACK_NON_BOLT);
    }

    public function isOrderManagementEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ORDER_MANAGEMENT);
    }

    public function isPayByLinkEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_PAY_BY_LINK);
    }

    public function isIgnoreHookForCreditMemoCreationEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_IGNORE_HOOK_FOR_CREDIT_MEMO_CREATION);
    }

    public function isIgnoreHookForInvoiceCreationEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_IGNORE_HOOK_FOR_INVOICE_CREATION);
    }

    public function isMerchantMetricsEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_MERCHANT_METRICS);
    }

    public function isInstantCheckoutButton()
    {
        return $this->isSwitchEnabled(Definitions::M2_INSTANT_BOLT_CHECKOUT_BUTTON);
    }

    public function isSaveHintsInSections()
    {
        return $this->isSwitchEnabled(Definitions::M2_SAVE_HINTS_IN_SECTIONS);
    }

    public function isAlwaysPresentCheckoutEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ALWAYS_PRESENT_CHECKOUT);
    }

    public function isSaveCartInSections() {
        return $this->isSwitchEnabled(Definitions::M2_SAVE_CART_IN_SECTIONS);
    }

    public function ifShouldDisablePrefillAddressForLoggedInCustomer() {
        return $this->isSwitchEnabled(Definitions::M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER);
    }

    public function handleVirtualProductsAsPhysical() {
        return $this->isSwitchEnabled(Definitions::M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL);
    }
}
