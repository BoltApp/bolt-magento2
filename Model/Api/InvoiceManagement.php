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

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Bolt\Boltpay\Api\InvoiceManagementInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Exception;


class InvoiceManagement implements InvoiceManagementInterface
{
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        Bugsnag $bugsnag
    ) {
        $this->invoiceRepository   = $invoiceRepository;
        $this->bugsnag      = $bugsnag;
    }

    /**
     * Get masked id for specified quote ID
     *
     * @api
     *
     * @param mixed $invoiceId
     * @param mixed $transactionId
     *
     * @return void
     *
     * @throws WebapiException
     */
    public function update($invoiceId = null, $transactionId = null)
    {
        try {
            $invoice = $this->invoiceRepository->get($invoiceId);
        } catch (Exception $e) {
            throw new WebapiException(__('Invoice does not found'), 0, WebapiException::HTTP_NOT_FOUND);
        }
        try {
            if ($transactionId) {
                $invoice->setTransactionId($transactionId)->save();
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}
