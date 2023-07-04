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

use Bolt\Boltpay\Model\Payment;
use Magento\Framework\View\Element\UiComponent\DataProvider\RegularFilter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Ui component filter plugin to implement filter sales order grid by bolt payment methods
 */
class RegularFilterPlugin
{
    private const PAYMENT_METHOD_FILTER_CODE = 'payment_method';

    private const BOLT_PAYMENT_METHOD_TYPE = 'bolt';

    private const DEFAULT_METHOD_TYPE = 'default';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Bugsnag $bugsnag
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Sales order grid filtering by bolt credit card type
     *
     * @param RegularFilter $subject
     * @param callable $proceed
     * @param Collection $collection
     * @param Filter $filter
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundApply(
        RegularFilter $subject,
        callable $proceed,
        Collection $collection,
        Filter $filter
    ) {
        try {
            $filterValue = $filter->getValue();
            if ($collection instanceof OrderGridCollection &&
                $filter->getField() == self::PAYMENT_METHOD_FILTER_CODE &&
                $filterValue &&
                $this->isFilterHasBoltPaymentMethod($filterValue)
            ) {
                $collectionSelect = $collection->getSelect();
                $collectionAdapter = $collectionSelect->getConnection();
                $paymentMethods = $this->getPaymentMethodsWithTypes($filterValue);
                $where = '';
                foreach ($paymentMethods as $paymentMethod) {
                    //if where statement already have some statement all next should be as OR
                    if ($where) {
                        $where .= ' OR ';
                    }
                    if ($paymentMethod['type'] == self::BOLT_PAYMENT_METHOD_TYPE) {
                        //bolt payment method where statement
                        $where .= '(main_table.payment_method = "'. Payment::METHOD_CODE . '"' .
                            ' AND ('. $collectionAdapter->quoteInto('LOWER(payment.additional_data) like ?', '%' . $paymentMethod['code'] . '%') .
                            ' OR '. $collectionAdapter->quoteInto('LOWER(payment.additional_information) like ?', '%' . $paymentMethod['code'] . '%') .
                            ' OR '. $collectionAdapter->quoteInto('LOWER(payment.cc_type) = ?', $paymentMethod['code']) .'))';
                    } else {
                        //default payment method where statement
                        $where .= '(main_table.payment_method = "'. $paymentMethod['code'] .'")';
                    }
                }
                $collectionSelect->where($where);
                return;
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return $proceed($collection, $filter);
        }
        return $proceed($collection, $filter);
    }

    /**
     * Returns payment methods with types
     *
     * @param string|array $paymentFilterValue
     * @return array
     */
    private function getPaymentMethodsWithTypes($paymentFilterValue): array
    {
        $paymentMethods = [];
        if (is_array($paymentFilterValue)) {
            foreach ($paymentFilterValue as $value) {
                $paymentMethods[] = [
                    'type' => $this->getPaymentMethodType($value),
                    'code' => $this->getPaymentMethodCode($value)
                ];
            }
        } else {
            $paymentMethods[] = [
                'type' => $this->getPaymentMethodType($paymentFilterValue),
                'code' => $this->getPaymentMethodCode($paymentFilterValue)
            ];
        }
        return $paymentMethods;
    }

    /**
     * Returns payment method type base on code
     *
     * @param string $paymentMethod
     * @return string
     */
    private function getPaymentMethodType(string $paymentMethod): string
    {
        return (strpos($paymentMethod, Payment::METHOD_CODE . '_') !== false) ? self::BOLT_PAYMENT_METHOD_TYPE
            : self::DEFAULT_METHOD_TYPE;
    }

    /**
     * Returns filtered payment method code without possible 'bolt' prefix
     *
     * @param string $paymentMethod
     * @return string
     */
    private function getPaymentMethodCode(string $paymentMethod): string
    {
        return str_replace(Payment::METHOD_CODE . '_', '', $paymentMethod);
    }

    /**
     * Checks if payment method filter contains bolt payment
     *
     * @param array|string $paymentFilterValue
     * @return bool
     */
    private function isFilterHasBoltPaymentMethod($paymentFilterValue): bool
    {
        return strpos(is_array($paymentFilterValue)
                ? implode(',', $paymentFilterValue)
                : (string)$paymentFilterValue, Payment::METHOD_CODE . '_') !== false;
    }

}
