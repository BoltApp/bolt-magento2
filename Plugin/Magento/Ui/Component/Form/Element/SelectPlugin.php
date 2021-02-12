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

namespace Bolt\Boltpay\Plugin\Magento\Ui\Component\Form\Element;

use Bolt\Boltpay\Helper\Order;

class SelectPlugin
{

    /**
     * Append individual Boltpay processors as payment method options to be rendered in order grid(s)
     * @see \Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderPlugin::afterGetData
     *
     * @param \Magento\Ui\Component\Form\Element\Select $subject
     * @param                                           $result
     * @return mixed
     */
    public function afterPrepare(\Magento\Ui\Component\Form\Element\Select $subject, $result)
    {
        if (in_array(
            $subject->getContext()->getNamespace(),
            [
                    'sales_order_grid',
                    'sales_order_invoice_grid',
                    'sales_order_creditmemo_grid',
                    'sales_order_shipment_grid',
                ]
        ) && $subject->getName() == 'payment_method') {
            $config = $subject->getData('config');
            foreach (array_merge(Order::TP_METHOD_DISPLAY, Order::SUPPORTED_CC_TYPES) as $key => $suffix) {
                $config['options'][] = [
                    'value'         => \Bolt\Boltpay\Model\Payment::METHOD_CODE . '_' . $key,
                    'label'         => 'Bolt-' . $suffix,
                    '__disableTmpl' => true
                ];
            }
            $subject->setData('config', $config);
        }
        return $result;
    }
}
