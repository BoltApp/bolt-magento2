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

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;

class CustomerBalance
{
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * CustomerBalance constructor.
     *
     * @param Config $configHelper
     */
    public function __construct(Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * Modifies checkout cart totals JS layout array with the purpose of adding components dynamically
     * If enabled in the config, adds Magento EE Store Credit button to the cart totals layout
     *
     * @param array $layout cart totals JS layout array
     *
     * @return array modified or unmodified JS layout array from the input
     */
    public function filterProcessLayout($layout)
    {
        // Store Credit
        if ($this->configHelper->useStoreCreditConfig()) {
            $layout['components']['block-totals']['children']['storeCredit'] = [
                'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance'
            ];
        }
        return $layout;
    }
}
