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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Model\Config\Source\Catalog\Ingestion;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

/**
 * Options provider for catalog ingestion product attributes sync.
 */
class Attributes implements OptionSourceInterface
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var []
     */
    private $disabledFrontendInputTypes = [
        'gallery'
    ];

    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(EavConfig $eavConfig)
    {
        $this->eavConfig = $eavConfig;
    }

    /**
     * Get product available attributes list.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $attributes = $this->eavConfig->getEntityAttributes(ProductAttributeInterface::ENTITY_TYPE_CODE);
        $options = [];
        foreach ($attributes as $attribute) {
            if (!in_array($attribute->getFrontendInput(),$this->disabledFrontendInputTypes)) {
                $options[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getAttributeCode(),
                ];
            }
        }
        return $options;
    }
}
