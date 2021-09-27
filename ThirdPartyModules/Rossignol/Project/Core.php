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
     * @var AttributeSetRepositoryInterface
     */
    private $attributeSetRepository;

    /**
     * Core constructor.
     *
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param BugsnagHelper $bugsnagHelper
     */
    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        BugsnagHelper $bugsnagHelper
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->bugsnagHelper = $bugsnagHelper;
    }
    
    /**
     * @param bool $result
     * @param \Project\Core\Helper\Sales $projectSalesHelper
     * @param \Magento\Framework\View\Element\Template $block
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function filterShouldDisableBoltCheckout(
        $result,
        $projectSalesHelper,
        $block,
        $quote
    ) {
        try {
            $disableTemplateList = ['Bolt_Boltpay::button_product_page.phtml'];
            $skiItemGroups = 'A1,B1,F1,H1,H2,A9,B9,F9';
            $apparelItemGroups = 'A2,A3,A5,B2,B3,F2,F3,H3,H4,H5,H6,H7,H8,H9,HA,N2,PS,S1,S8,TA,TB,TC,TD,TE,TF,TG,TH,TI,TJ,TK,TL,TM,TN,TO,TP,TQ,TR,TS,TT,TU,TW,TX,TY,TZ,L1,N5,R1,S7,TV';
            if (!$result
                && 'catalog_product_view' === $block->getRequest()->getFullActionName()
                && in_array($block->getTemplate(), $disableTemplateList)) {
                $product = $block->getProduct();
                $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
                $attributeSetName = $attributeSet->getAttributeSetName();
                if (in_array($attributeSetName, explode(',', $skiItemGroups))) {
                    $isKit = $projectSalesHelper->isProductWithKit($product);
                    $canSell = $projectSalesHelper->isEcommFeaturesEnabled() && ($isKit || $projectSalesHelper->canSell($product));
                } elseif (in_array($attributeSetName, explode(',', $apparelItemGroups))) {
                    $canSell = $projectSalesHelper->canSell($product);
                } else {
                    $canSell = $product->isSaleable();
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
