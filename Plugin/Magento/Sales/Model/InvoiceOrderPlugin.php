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

namespace Bolt\Boltpay\Plugin\Magento\Sales\Model;

use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Api\InvoiceRepositoryInterface;

class InvoiceOrderPlugin
{
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Aheadworks Store Credit extension doesn't apply points if invoice created via API call
     * It happens because the module expected to see direct save call $invoice->save() but API
     * saves invoice via resource model
     * So in this plugin we trigger direct invoice saving after API call
     * This plugin should be reverted once issue is fixed on Aheadworks Store Credit side.
     *
     * @param InvoiceOrder $subject
     * @param int $result
     * @return int
     */
    public function afterExecute(InvoiceOrder $subject, $result)
    {
        $invoice = $this->invoiceRepository->get($result);
        $invoice->save();

        return $result;
    }
}
