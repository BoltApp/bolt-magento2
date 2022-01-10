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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;

class Preorder
{
    const AMASTY_PREORDER_ATTRIBUTE_NAME = 'amasty_preorder_note'; 
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * Preorder constructor.
     * @param Bugsnag $bugsnagHelper
     */
    public function __construct(
        Bugsnag $bugsnagHelper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * Add Amasty preorder note to the value of additional attribute when needed.
     *
     * @param array $result
     * @param \Amasty\Preorder\Helper\Data $preOrderHelper
     * @param string $sku
     * @param string $storeId
     * @param string $attributeName
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return array
     */
    public function filterCartItemsAdditionalAttributeValue(
        $result,
        $preOrderHelper,
        $sku,
        $storeId,
        $attributeName,
        $product
    ) {
        try {
            if (self::AMASTY_PREORDER_ATTRIBUTE_NAME === $attributeName && $preOrderHelper->getIsProductPreorder($product)) {
                $result = $preOrderHelper->getProductPreorderNote($product);
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
