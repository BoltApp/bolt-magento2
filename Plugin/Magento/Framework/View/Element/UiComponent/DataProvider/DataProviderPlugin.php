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

namespace Bolt\Boltpay\Plugin\Magento\Framework\View\Element\UiComponent\DataProvider;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Order;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory;

class DataProviderPlugin
{
    /**
     * @var CollectionFactory Order payment collection factory
     */
    private $paymentCollectionFactory;

    /**
     * @var Config Bolt configuration helper
     */
    private $configHelper;

    /**
     * DataProviderPlugin constructor.
     * @param CollectionFactory $paymentCollectionFactory
     * @param Config            $configHelper
     */
    public function __construct(CollectionFactory $paymentCollectionFactory, Config $configHelper)
    {
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->configHelper = $configHelper;
    }

    /**
     * Plugin for {@see \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider::getData}
     * Appends payment processor to the Bolt orders payment method code for the following grids:
     * 1. Sales Order Grid
     * 2. Order Invoice Grid
     * 3. Creditmemo Grid
     * 4. Shipments Grid
     *
     * @param DataProvider $subject
     * @param array        $result
     *
     * @return array
     */
    public function afterGetData(DataProvider $subject, $result)
    {
        if (empty($result['items'])) {
            return $result;
        }
        if (!in_array(
            $subject->getName(),
            [
                'sales_order_grid_data_source',
                'sales_order_invoice_grid_data_source',
                'sales_order_creditmemo_grid_data_source',
                'sales_order_shipment_grid_data_source'
            ]
        )) {
            return $result;
        }
        $ids = array_column(
            array_filter(
                $result['items'],
                function ($item) {
                    return $item['payment_method'] == \Bolt\Boltpay\Model\Payment::METHOD_CODE;
                }
            ),
            key_exists('order_id', $result['items'][0]) ? 'order_id' : 'entity_id'
        );
        $paymentCollection = $this->paymentCollectionFactory->create()
            ->addFieldToFilter('parent_id', ['in' => $ids]);
        $showCcTypeInOrderGrid = $this->configHelper->getShowCcTypeInOrderGrid();
        foreach ($result['items'] as &$item) {
            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $paymentCollection->getItemByColumnValue(
                'parent_id',
                key_exists('order_id', $item) ? $item['order_id'] : $item['entity_id']
            );
            if (!$payment) {
                continue;
            }
            if ($showCcTypeInOrderGrid &&
                key_exists(
                    $ccType = strtolower($payment->getCcType()),
                    Order::SUPPORTED_CC_TYPES
                )
            ) {
                $item['payment_method'] .= '_' . $ccType;
                continue;
            }
            if ($payment->getCcTransId()) {
                // api flow, title rendered on server side
                $title = $payment->getAdditionalData();
                if ($title && ($intersection = array_intersect([strtolower (str_replace('Bolt-', '', $title))], array_keys(Order::TP_METHOD_DISPLAY)))) {
                    $item['payment_method'] = \Bolt\Boltpay\Model\Payment::METHOD_CODE . '_' . reset($intersection);//str_replace('-', '_', $title);
                }
                continue;
            }
            if ($intersection = array_intersect(
                [$payment->getData('additional_information/processor'), $payment->getAdditionalData()],
                array_keys(Order::TP_METHOD_DISPLAY)
            )) {
                $item['payment_method'] .= '_' . reset($intersection);
            }
        }
        return $result;
    }
}
