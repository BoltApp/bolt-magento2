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

namespace Bolt\Boltpay\ThirdPartyModules\Rossignol\Project;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Bugsnag as BugsnagHelper;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;

/**
 * Class Core
 *
 * @package Bolt\Boltpay\ThirdPartyModules\Rossignol
 */
class Core
{    
    /**
     * @var BugsnagHelper
     */
    private $bugsnagHelper;

    /**
     * Core constructor.
     *
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        BugsnagHelper $bugsnagHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
    }
    
    /**
     * @param bool $result
     * @param \Project\Core\Helper\Sales $restrictSalesHelper
     * @param \Magento\Framework\View\Element\Template $block
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function filterShouldDisableBoltCheckout(
        $result,
        $restrictSalesHelper,
        $block,
        $quote
    ) {
        try {
            $disableTemplateList = ['Bolt_Boltpay::button_product_page.phtml'];
            if (!$result
                && 'catalog_product_view' === $block->getRequest()->getFullActionName()
                && in_array($block->getTemplate(), $disableTemplateList)) {
                $product = $block->getProduct();
                $canSell = $restrictSalesHelper->canSell($product);
                $isKit   = $restrictSalesHelper->isProductWithKit($product);
                if ($isKit) {
                    $canSell = $restrictSalesHelper->isEcommFeaturesEnabled();
                }
                $result = !$canSell;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
