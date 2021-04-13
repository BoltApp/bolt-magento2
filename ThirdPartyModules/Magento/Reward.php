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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\ThirdPartyEvents\FiltersCartTotalsLayout;
use Bolt\Boltpay\Model\ThirdPartyEvents\FiltersMinicartAddonsLayout;

class Reward
{
    use FiltersCartTotalsLayout;
    use FiltersMinicartAddonsLayout;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    private $httpContext;

    /**
     * Reward constructor.
     *
     * @param Config                              $configHelper
     * @param \Magento\Framework\App\Http\Context $httpContext
     */
    public function __construct(Config $configHelper, \Magento\Framework\App\Http\Context $httpContext)
    {
        $this->configHelper = $configHelper;
        $this->httpContext = $httpContext;
    }

    /**
     * @param $layout
     *
     * @return mixed
     */
    public function filterProcessLayout($layout)
    {
        // Reward Points
        if ($this->configHelper->useRewardPointsConfig()) {
            $layout['components']['block-totals']['children']['rewardPoints'] = [
                'component' => 'Magento_Reward/js/view/payment/reward'
            ];
        }
        return $layout;
    }

    /**
     * Modifies minicart addons layout array with the purpose of adding components dynamically
     * Adds Magento EE reward points add/remove button and total display components
     *
     * @param array $layout minicart layout array
     *
     * @return array modified or unmodified layout array from the input
     */
    public function filterMinicartAddonsLayout($layout)
    {
        if ($this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH)
            && $this->configHelper->displayRewardPointsInMinicartConfig()) {
            $layout[] = [
                'parent'    => 'minicart_content.extra_info',
                'name'      => 'minicart_content.extra_info.rewards',
                'component' => 'Magento_Reward/js/view/payment/reward',
                'config'    => [],
            ];
            $layout[] = [
                'parent'    => 'minicart_content.extra_info',
                'name'      => 'minicart_content.extra_info.rewards_total',
                'component' => 'Magento_Reward/js/view/cart/reward',
                'config'    => [
                    'template' => 'Magento_Reward/cart/reward',
                    'title'    => 'Reward Points',
                ],
            ];
        }
        return $layout;
    }
}
