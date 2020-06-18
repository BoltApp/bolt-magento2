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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Block;

/**
 * Plugin for {@see \Magento\Customer\Block\SectionConfig}
 * Used to dynamically configure the 'boltcart' section's invalidation to be triggered at
 * the same time that Magento's 'cart' section is invalidated. This way any (core or third party)
 * actions that invalidate the cart will also invalidate the 'boltcart' section and subsequently
 * trigger a new Bolt order creation.
 */
class SectionConfigPlugin
{
    /**
     * Plugin for {@see \Magento\Customer\Block\SectionConfig::getSections}
     * Appends 'boltcart' to the list of invalidated sections of every action that currently invalidates the 'cart' section
     *
     * @param \Magento\Customer\Block\SectionConfig $subject plugged instance of the section configuration block
     * @param array                                 $result original list of sections for invalidation
     *
     * @return array list of sections for invalidation appended with boltcart section wherever applicable
     */
    public function afterGetSections(\Magento\Customer\Block\SectionConfig $subject, $result)
    {
        foreach ($result as &$section) {
            if (is_array($section) && in_array('cart', $section) && !in_array('boltcart', $section)) {
                $section[] = 'boltcart';
            }
        }
        return $result;
    }
}
