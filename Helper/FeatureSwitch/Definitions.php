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

/**
 * All feature switches are defined here. By default all new switches should be
 * false and not rolled out. These values are only fallbacks for when something
 * isn't defined on the Bolt side. Anything values coming from Bolt take presedence.
 *
 * Class Definitions
 * @package Bolt\Boltpay\Helper\FeatureSwitch
 */
class Definitions {
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

    const DEFAULT_SWITCH_VALUES = array(
        self::M2_SAMPLE_SWITCH_NAME =>  array(
          self::NAME_KEY            => self::M2_SAMPLE_SWITCH_NAME,
          self::VAL_KEY             => true,
          self::DEFAULT_VAL_KEY     => false,
          self::ROLLOUT_KEY         => 0
        ),
        self::M2_BOLT_ENABLED =>  array(
          self::NAME_KEY            => self::M2_BOLT_ENABLED,
          self::VAL_KEY             => true,
          self::DEFAULT_VAL_KEY     => false,
          self::ROLLOUT_KEY         => 100
        ),
        self::M2_LOG_MISSING_QUOTE_FAILED_HOOKS =>  array(
            self::NAME_KEY            => self::M2_LOG_MISSING_QUOTE_FAILED_HOOKS,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 100
        ),
        self::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER =>  array(
            self::NAME_KEY            => self::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        ),
        self::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED =>  array(
            self::NAME_KEY            => self::M2_CREATING_CREDITMEMO_FROM_WEB_HOOK_ENABLED,
            self::VAL_KEY             => true,
            self::DEFAULT_VAL_KEY     => false,
            self::ROLLOUT_KEY         => 0
        )
    );
}