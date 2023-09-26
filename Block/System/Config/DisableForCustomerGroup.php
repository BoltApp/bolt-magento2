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

namespace Bolt\Boltpay\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;

class DisableForCustomerGroup extends Field
{
    /**
     * @var \Bolt\Boltpay\Helper\FeatureSwitch\Decider
     */
    protected $_featureSwitch;

    public function __construct(
        Context                                    $context,
        \Bolt\Boltpay\Helper\FeatureSwitch\Decider $_featureSwitch,
        array                                      $data = []
    )
    {
        $this->_featureSwitch = $_featureSwitch;
        parent::__construct($context, $data);
    }

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->_featureSwitch->isAllowDisablingBoltForCustomerGroup()) {
            return parent::render($element); // TODO: Change the autogenerated stub
        }

        return '';
    }
}