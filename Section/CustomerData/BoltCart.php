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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Section\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\App\Response\RedirectInterface;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\UrlInterface;

class BoltCart implements SectionSourceInterface
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * BoltCart constructor.
     * @param CartHelper $cartHelper
     * @param RedirectInterface $redirect
     * @param Config $configHelper
     * @param UrlInterface $url
     */
    public function __construct(
        CartHelper $cartHelper,
        RedirectInterface $redirect,
        Config $configHelper,
        UrlInterface $url
    )
    {
        $this->cartHelper = $cartHelper;
        $this->redirect = $redirect;
        $this->configHelper = $configHelper;
        $this->url = $url;
    }

    public function getSectionData()
    {
        if ($this->isBoltUsedInCheckoutPage()) {
            return $this->cartHelper->calculateCartAndHints(true);
        }

        return $this->cartHelper->calculateCartAndHints();
    }

    /**
     * @return bool
     */
    private function isBoltUsedInCheckoutPage()
    {
        $checkoutUrl = $this->url->getUrl('checkout');
        $refererUrl = $this->redirect->getRefererUrl();
        return (trim($refererUrl, '/') == trim($checkoutUrl, '/') && $this->configHelper->isPaymentOnlyCheckoutEnabled());
    }
}
