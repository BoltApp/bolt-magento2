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

namespace Bolt\Boltpay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ShipmentTypeCategory implements ArrayInterface
{
    const PICKINSTORE = 'pick_in_store';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::PICKINSTORE,
                'label' => __('Pick in Store')
            ]
        ];

        return $options;
    }
    
    /**
     * Return available HS regions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (empty($this->options)) {
            $this->options[] = [
                'value' => '',
                'label' => __('--Please Select--')
            ];
            
            $this->options[] = [
                'value' => self::PICKINSTORE,
                'label' => __('Pick in Store')
            ];
        }

        return $this->options;
    }
    
    /**
     * Rewrite method for using in mass update attribute
     * @param $attribute
     * @return $this
     */
    public function setAttribute($attribute)
    {
        return $this;
    }
}