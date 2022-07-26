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

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ShipmentType extends AbstractSource
{
    const SHIPTOSTORE = 'ship_to_store';
    const SHIPTOHOMEONLY = 'ship_to_home_only';

    /**
     * Get custom options
     *
     * @return array
     */
    public function getOptionArray()
    {
        $options[self::SHIPTOSTORE] =  __('Ship to Store');
        $options[self::SHIPTOHOMEONLY] =  __('Ship to Home Only');
        
        return $options;
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions()
    {
        $res = $this->getOptions();
        array_unshift($res, ['value' => '', 'label' => __('--Please Select--')]);
        return $res;
    }

    /**
     * Get options function
     *
     * @return array
     */
    public function getOptions()
    {
        $res = [];
        foreach ($this->getOptionArray() as $index => $value) {
            $res[] = ['value' => $index, 'label' => $value];
        }
        return $res;
    }

    /**
     * To option array function
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptions();
    }
}
