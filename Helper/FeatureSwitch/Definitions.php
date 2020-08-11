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

/**
 * All feature switches are defined here. By default all new switches should be
 * false and not rolled out. These values are only fallbacks for when something
 * isn't defined on the Bolt side. Anything values coming from Bolt take precedence.
 *
 * Class Definitions
 * @package Bolt\Boltpay\Helper\FeatureSwitch
 */
class Definitions
{
    const NAME_KEY="name";
    const VAL_KEY="value";
    const DEFAULT_VAL_KEY="default_value";
    const ROLLOUT_KEY="rollout_percentage";

    /**
     * This switch is a sample of how to set up a feature switch.
     * Every feature switch added here should have a corresponding helper
     * in Decider.php
     */
    const M2_SAMPLE_SWITCH_NAME = "M2_SAMPLE_SWITCH";

    /**
     * Whether bolt is enabled. This will be used for slow rollout / controlling bolt
     * from the backend.
     */
    const M2_BOLT_ENABLED = "M2_BOLT_ENABLED";

    /**
     * Enable logging of missing quote failed hooks
     *
     */
    const M2_LOG_MISSING_QUOTE_FAILED_HOOKS = "M2_LOG_MISSING_QUOTE_FAILED_HOOKS";

    /**
     * Enable creating credit memo for webhook
     *
     */
    const M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED = "M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED";

    /**
     * Enable feature/admin reorder for logged in customer
     */
    const M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER = "M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER";

    /**
     * Enable tracking shipment
     */
    const M2_TRACK_SHIPMENT = "M2_TRACK_SHIPMENT";

    /**
     * Enable non-Bolt order tracking
     */
    const M2_TRACK_NON_BOLT = "M2_TRACK_NON_BOLT";

    /**
     * Enable Order Management (account button)
     */
    const M2_ORDER_MANAGEMENT = "M2_ORDER_MANAGEMENT";

    /**
     * Enable Pay-by-link feature
     */
    const M2_PAY_BY_LINK = "M2_PAY_BY_LINK";

    /**
     * Enable ignore hook for invoice creation feature
     */
    const M2_IGNORE_HOOK_FOR_INVOICE_CREATION = "M2_IGNORE_HOOK_FOR_INVOICE_CREATION";

    /**
     * Enable ignore hook for credit memo creation feature
     */
    const M2_IGNORE_HOOK_FOR_CREDIT_MEMO_CREATION = "M2_IGNORE_HOOK_FOR_CREDIT_MEMO_CREATION";

    /**
     * Enable merchant metrics
     */
    const M2_MERCHANT_METRICS = "M2_MERCHANT_METRICS";

    /**
     * Enable Bolt Button V2
     */
    const M2_INSTANT_BOLT_CHECKOUT_BUTTON = "M2_INSTANT_BOLT_CHECKOUT_BUTTON";

    /**
     * Save hints data for PPC in magento sections
     */
    const M2_SAVE_HINTS_IN_SECTIONS = "M2_SAVE_HINTS_IN_SECTIONS";

    /**
     * Enable always present checkout button
     */
    const M2_ALWAYS_PRESENT_CHECKOUT = "M2_ALWAYS_PRESENT_CHECKOUT";

    /**
     * Use magento sections to save bolt cart in browser local storage
     */
    const M2_SAVE_CART_IN_SECTIONS = "M2_SAVE_CART_IN_SECTIONS";

    /**
     * Disable pre-fill address from Bolt for the logged-in customer
     */
    const M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER = "M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER";

    /**
     * Handle virtual cart as physical. Workarond for known issue with taxable virtual products.
     */
    const M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL = "M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL";

    const DEFAULT_SWITCH_VALUES = [
        self::M2_SAMPLE_SWITCH_NAME =>  [
          self::NAME_KEY            => self::M2_SAMPLE_SWITCH_NAME,
          self::VAL_KEY             => true,
          self::DEFAULT_VAL_KEY     => false,
          self::ROLLOUT_KEY         => 0
        ],
        self::M2_BOLT_ENABLED =>  [
          self::NAME_KEY            => self::M2_BOLT_ENABLED,
          self::VAL_KEY             => true,
          self::DEFAULT_VAL_KEY     => false,
          self::ROLLOUT_KEY         => 100
        ],
        self::M2_LOG_MISSING_QUOTE_FAILED_HOOKS =>  [
            self::NAME_KEY            => self::M2_LOG_MISSING_QUOTE_FAILED_HOOKS,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER =>  [
            self::NAME_KEY            => self::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED =>  [
            self::NAME_KEY            => self::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_TRACK_SHIPMENT => [
            self::NAME_KEY            => self::M2_TRACK_SHIPMENT,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_TRACK_NON_BOLT => [
            self::NAME_KEY            => self::M2_TRACK_NON_BOLT,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_ORDER_MANAGEMENT =>  [
            self::NAME_KEY            => self::M2_ORDER_MANAGEMENT,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_PAY_BY_LINK =>  [
            self::NAME_KEY            => self::M2_PAY_BY_LINK,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_IGNORE_HOOK_FOR_CREDIT_MEMO_CREATION =>  [
            self::NAME_KEY            => self::M2_IGNORE_HOOK_FOR_CREDIT_MEMO_CREATION,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_IGNORE_HOOK_FOR_INVOICE_CREATION =>  [
            self::NAME_KEY            => self::M2_IGNORE_HOOK_FOR_INVOICE_CREATION,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_MERCHANT_METRICS => [
            self::NAME_KEY            => self::M2_MERCHANT_METRICS,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_INSTANT_BOLT_CHECKOUT_BUTTON => [
            self::NAME_KEY            => self::M2_INSTANT_BOLT_CHECKOUT_BUTTON,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_SAVE_HINTS_IN_SECTIONS => [
            self::NAME_KEY            => self::M2_SAVE_HINTS_IN_SECTIONS,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_ALWAYS_PRESENT_CHECKOUT => [
            self::NAME_KEY            => self::M2_ALWAYS_PRESENT_CHECKOUT,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_SAVE_CART_IN_SECTIONS => [
            self::NAME_KEY            => self::M2_SAVE_CART_IN_SECTIONS,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ],
        self::M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER =>  [
            self::NAME_KEY            => self::M2_IF_SHOULD_DISABLE_PREFILL_ADDRESS_FROM_BOLT_FOR_LOGGED_IN_CUSTOMER,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
        self::M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL => [
            self::NAME_KEY            => self::M2_HANDLE_VIRTUAL_PRODUCTS_AS_PHYSICAL,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ],
    ];
}
