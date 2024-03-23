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
 *
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block\Checkout;

use Bolt\Boltpay\Block\BlockTrait;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Session\SessionManager as CheckoutSession;

class Success extends Template
{
    use BlockTrait;

    /**
     * Success constructor.
     *
     * @param Config                     $configHelper
     * @param Context                    $context
     * @param Decider                    $featureSwitches
     * @param CheckoutSession            $checkoutSession
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param array   $data
     */
    public function __construct(
        Config $configHelper,
        Context $context,
        Decider $featureSwitches,
        CheckoutSession $checkoutSession,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        HttpContext $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->featureSwitches = $featureSwitches;
        $this->checkoutSession = $checkoutSession;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->httpContext = $httpContext;
    }

    /**
     * Get Magento version
     * @return string
     */
    public function getMagentoVersion() {
        return $this->configHelper->getStoreVersion();
    }
}
