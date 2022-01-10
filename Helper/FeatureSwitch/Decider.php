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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper\FeatureSwitch;

use Bolt\Boltpay\Model\FeatureSwitchFactory;
use Bolt\Boltpay\Model\FeatureSwitchRepository;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;

class Decider extends AbstractHelper
{
    /**
     * @var Manager
     */
    private $_manager;

    /**
     * @var State
     */
    private $_state;

    /**
     * @var CoreSession
     */
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
     * @param Context                 $context
     * @param CoreSession             $coreSession
     * @param State                   $state
     * @param Manager                 $manager
     * @param FeatureSwitchFactory    $fsFactory
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
     * @param int    $rolloutPercentage
     *
     * @return bool
     */
    private function _isInBucket(string $switchName, int $rolloutPercentage)
    {
        $session = $this->_session->start();
        $boltFeatureSwitchId = $session->getBoltFeatureSwitchId();

        if (!$boltFeatureSwitchId) {
            $boltFeatureSwitchId = uniqid("BFS", true);
            $session->setBoltFeatureSwitchId($boltFeatureSwitchId);
        }
        $saltedString = $boltFeatureSwitchId . "-" . $switchName;
        $hash = hash('md5', $saltedString);
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
        $defaultDef = Definitions::DEFAULT_SWITCH_VALUES[$switchName] ?? null;
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
        } elseif ($switch->getRolloutPercentage() == 100) {
            $isSwitchEnabled = $switch->getValue();
        } else {
            $isInBucket = $this->_isInBucket($switchName, $switch->getRolloutPercentage());
            $isSwitchEnabled = $isInBucket ? $switch->getValue() : $switch->getDefaultValue();
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
     * Checks whether the feature switch for updating orders is enabled
     */
    public function isOrderUpdateEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ORDER_UPDATE);
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

    public function ifShouldDisablePrefillAddressForLoggedInCustomer()
    {
        return $this->isSwitchEnabled(Definitions::M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER);
    }

    public function handleVirtualProductsAsPhysical()
    {
        return $this->isSwitchEnabled(Definitions::M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL);
    }

    public function isIncludeUserGroupIntoCart()
    {
        return $this->isSwitchEnabled(Definitions::M2_INCLUDE_USER_GROUP_ID_INTO_CART);
    }

    public function isCaptureEmailToListrakEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_CAPTURE_EMAIL_TO_LISTRAK_ENABLED);
    }

    public function isPrefetchShippingEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_PREFETCH_SHIPPING);
    }

    public function ifShouldDisableRedirectCustomerToCartPageAfterTheyLogIn()
    {
        return $this->isSwitchEnabled(Definitions::M2_IF_SHOULD_DISABLE_REDIRECT_CUSTOMER_TO_CART_PAGE_AFTER_THEY_LOG_IN);
    }

    public function isCancelFailedPaymentOrderInsteadOfDeleting()
    {
        return $this->isSwitchEnabled(Definitions::M2_CANCEL_FAILED_PAYMENT_ORDERS_INSTEAD_OF_DELETING);
    }

    public function isAddSessionIdToCartMetadata()
    {
        return $this->isSwitchEnabled(Definitions::M2_ADD_SESSION_ID_TO_CART_METADATA);
    }

    public function isBoltSSOEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ENABLE_BOLT_SSO);
    }

    public function isCustomizableOptionsSupport()
    {
        return $this->isSwitchEnabled(Definitions::M2_CUSTOMIZABLE_OPTIONS_SUPPORT);
    }

    public function isLoadConnectJsOnSpecificPage()
    {
        return $this->isSwitchEnabled(Definitions::M2_LOAD_CONNECT_JS_ON_SPECIFIC_PAGE);
    }
    
    public function isDisableTrackJsOnHomePage()
    {
        return $this->isSwitchEnabled(Definitions::M2_DISABLE_TRACK_ON_HOME_PAGE);
    }
    
    public function isDisableTrackJsOnNonBoltPages()
    {
        return $this->isSwitchEnabled(Definitions::M2_DISABLE_TRACK_ON_NON_BOLT_PAGES);
    }

    public function isReturnErrWhenRunFilter()
    {
        return $this->isSwitchEnabled(Definitions::M2_RETURN_ERR_WHEN_RUN_FILTER);
    }

    /**
     * Checks whether the feature switch for displaying order comment in admin is enabled
     */
    public function isShowOrderCommentInAdmin()
    {
        return $this->isSwitchEnabled(Definitions::M2_SHOW_ORDER_COMMENT_IN_ADMIN);
    }

    /**
     * Checks whether the feature switch for preventing bolt cart for quotes with error is enabled
     *
     * @return bool
     */
    public function isPreventBoltCartForQuotesWithError()
    {
        return $this->isSwitchEnabled(Definitions::M2_PREVENT_BOLT_CART_FOR_QUOTES_WITH_ERROR);
    }

    /**
     * Checks whether the feature switch for setting order payment info data on success page is enabled
     *
     * @return bool
     */
    public function isSetOrderPaymentInfoDataOnSuccessPage()
    {
        return $this->isSwitchEnabled(Definitions::M2_SET_ORDER_PAYMENT_INFO_DATA_ON_SUCCESS_PAGE);
    }

    /**
     * Checks whether the feature switch for seting customer name to order for guests is enabled
     *
     * @return bool
     */
    public function isSetCustomerNameToOrderForGuests()
    {
        return $this->isSwitchEnabled(Definitions::M2_SET_CUSTOMER_NAME_TO_ORDER_FOR_GUESTS);
    }

    /**
     * Checks whether the feature switch for saving customer credit card is enabled
     */
    public function isSaveCustomerCreditCardEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_SAVE_CUSTOMER_CREDIT_CARD);
    }

    public function isIgnoreTotalValidationWhenCreditHookIsSentToMagentoEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_IGNORE_TOTAL_VALIDATION_WHEN_CREDIT_HOOK_IS_SENT_TO_MAGENTO);
    }

    /**
     * Checks whether the feature switch to prevent setting Bolt IPs as customer IP on quote is enabled
     */
    public function isPreventSettingBoltIpsAsCustomerIpOnQuote()
    {
        return $this->isSwitchEnabled(Definitions::M2_PREVENT_SETTING_BOLT_IPS_AS_CUSTOMER_IP_ON_QUOTE);
    }

    public function isProductEndpointEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ENABLE_PRODUCT_ENDPOINT);
    }

    /**
     * Checks whether the feature switch for preventing the plugin method from overriding the order status
     */
    public function isDisallowOrderStatusOverride()
    {
        return $this->isSwitchEnabled(Definitions::M2_DISALLOW_ORDER_STATUS_OVERRIDE);
    }

    public function isIncludeMismatchAmountIntoTaxWhenAdjustingPriceMismatch()
    {
        return $this->isSwitchEnabled(Definitions::M2_INCLUDE_MISMATCH_AMOUNT_INTO_TAX_WHEN_ADJUSTING_PRICE_MISMATCH);
    }
    
    public function isAPIDrivenIntegrationEnabled()
    {
        return $this->isSwitchEnabled(Definitions::M2_ENABLE_API_DRIVEN_INTEGRAION);
    }

    public function isRecalculateTotalForAPIDrivenIntegration()
    {
        return $this->isSwitchEnabled(Definitions::M2_RECALCULATE_TOTAL_FOR_API_DRIVEN_INTEGRAION);
    }
    
    public function isCollectDiscountsByPlugin()
    {
        return $this->isSwitchEnabled(Definitions::M2_COLLECT_DISCOUNT_BY_PLUGIN);
    }

    /**
     * Determines if SSO customers should be prevented from editing their account and address data
     *
     * @return bool whether the feature switch is enabled
     *
     * @throws LocalizedException if the feature switch key is unknown
     */
    public function isPreventSSOCustomersFromEditingAccountInformation()
    {
        return $this->isSwitchEnabled(Definitions::M2_PREVENT_SSO_CUSTOMERS_FROM_EDITING_ACCOUNT_INFORMATION);
    }
    
    public function isAllowCustomURLForProduction()
    {
        return $this->isSwitchEnabled(Definitions::M2_ALLOW_CUSTOM_CDN_URL_FOR_PRODUCTION);
    }
}
