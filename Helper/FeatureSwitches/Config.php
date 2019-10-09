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

namespace Bolt\Boltpay\Helper\FeatureSwitches;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Config extends AbstractHelper {
    const TABLE_NAME = 'bolt_feature_switches';

    const SWITCH_NAME_COL = 'switch_name';
    const SWITCH_VALUE_COL = 'switch_value';
    const DEFAULT_VALUE_COL = 'default_value';
    const ROLLOUT_PERCENTAGE_COL = 'rollout_percentage';

    /**
     * @param Context $context
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }
}