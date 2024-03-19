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
        if ($this->isResultEmptyOrIrrelevant($result, $subject)) {
            return $result;
        }

        $ids = $this->getBoltOrderIds($result['items']);
        $paymentCollection = $this->getPaymentCollection($ids);
        $showCcTypeInOrderGrid = $this->configHelper->getShowCcTypeInOrderGrid();

        foreach ($result['items'] as &$item) {
            $payment = $this->getPaymentFromCollection($paymentCollection, $item);
            if (!$payment) {
                continue;
            }

            $this->updatePaymentMethod($item, $payment, $showCcTypeInOrderGrid);
        }

        return $result;
    }

    /**
     * Returns payment collection
     *
     * @param array $ids
     * @return \Magento\Sales\Model\ResourceModel\Order\Payment\Collection
     */
    private function getPaymentCollection(array $ids)
    {
        return $this->paymentCollectionFactory->create()
            ->addFieldToFilter('parent_id', ['in' => $ids]);
    }

    /**
     * Checks if result is empty or irrelevant
     *
     * @param $result
     * @param $subject
     * @return bool
     */
    private function isResultEmptyOrIrrelevant($result, $subject): bool
    {
        $relevantGrids = [
            'sales_order_grid_data_source',
            'sales_order_invoice_grid_data_source',
            'sales_order_creditmemo_grid_data_source',
            'sales_order_shipment_grid_data_source'
        ];

        return empty($result['items']) || !in_array($subject->getName(), $relevantGrids);
    }

    /**
     * Returns Bolt order ids
     *
     * @param array $items
     * @return array
     */
    private function getBoltOrderIds(array $items): array
    {
        return array_column(
            array_filter(
                $items,
                function ($item) {
                    return $item['payment_method'] == \Bolt\Boltpay\Model\Payment::METHOD_CODE;
                }
            ),
            key_exists('order_id', $items[0]) ? 'order_id' : 'entity_id'
        );
    }

    /**
     * Returns payment from collection
     *
     * @param $paymentCollection
     * @param array $item
     * @return mixed
     */
    private function getPaymentFromCollection($paymentCollection, array $item)
    {
        return $paymentCollection->getItemByColumnValue(
            'parent_id',
            key_exists('order_id', $item) ? $item['order_id'] : $item['entity_id']
        );
    }

    /**
     * Updates payment method in the grid
     *
     * @param array $item
     * @param $payment
     * @param $showCcTypeInOrderGrid
     * @return void
     */
    private function updatePaymentMethod(array &$item, $payment, $showCcTypeInOrderGrid): void
    {
        $ccType = $payment->getCcType();
        if ($showCcTypeInOrderGrid && $this->isSupportedCcType($ccType)) {
            $item['payment_method'] .= '_' . strtolower((string)$ccType);
            return;
        }

        if ($payment->getCcTransId() && $this->isApiFlow($payment)) {
            $item['payment_method'] = \Bolt\Boltpay\Model\Payment::METHOD_CODE . '_' . $this->getApiFlowMethod($payment);
            return;
        }

        $thirdPartyMethod = $this->getThirdPartyMethod($payment);
        if ($thirdPartyMethod) {
            $item['payment_method'] .= '_' . $thirdPartyMethod;
        }
    }

    /**
     * Checks if cc type is supported
     *
     * @param $ccType
     * @return bool
     */
    private function isSupportedCcType($ccType): bool
    {
        return !empty($ccType) && key_exists(strtolower((string)$ccType), Order::SUPPORTED_CC_TYPES);
    }

    /**
     * Checks if payment method is API flow
     *
     * @param $payment
     * @return bool
     */
    private function isApiFlow($payment): bool
    {
        return $payment->getAdditionalData() && array_intersect([strtolower(str_replace('Bolt-', '', $payment->getAdditionalData()))], array_keys(Order::TP_METHOD_DISPLAY));
    }

    /**
     * Returns API flow method
     *
     * @param $payment
     * @return string
     */
    private function getApiFlowMethod($payment): string
    {
        $intersectResult = array_intersect([strtolower(str_replace('Bolt-', '', $payment->getAdditionalData()))], array_keys(Order::TP_METHOD_DISPLAY));
        return reset($intersectResult);
    }

    /**
     * Returns third party method
     *
     * @param $payment
     * @return string|null
     */
    private function getThirdPartyMethod($payment): ?string
    {
        $intersectResult = array_intersect([$payment->getData('additional_information/processor'), $payment->getAdditionalData()], array_keys(Order::TP_METHOD_DISPLAY));
        if (!empty($intersectResult)) {
            return reset($intersectResult);
        }
        return null;
    }
}
