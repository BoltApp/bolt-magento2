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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\InvoiceManagement;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Sales\Api\InvoiceRepositoryInterface;

/**
 * Class CartInterfaceTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\InvoiceManagemnt
 */
class InvoiceManagementTest extends BoltTestCase
{
    const REFERENCE = 'B74N-PQXW-PYQ9';

    /** array of objects we need to delete after test */
    private $objectsToClean;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectsToClean = [];
        $this->objectManager = Bootstrap::getObjectManager();
        $this->invoiceManagement = $this->objectManager->create(InvoiceManagement::class);
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures($this->objectsToClean);
    }

   
    /**
     * @test
     * @covers ::update
     */
    public function update_happyPath()
    {
        $invoice = TestUtils::createInvoice();
        $invoiceId = $invoice->getId();

        $response = $this->invoiceManagement->update($invoiceId, self::REFERENCE);

        $invoiceRepository = $this->objectManager->create(InvoiceRepositoryInterface::class);
        $invoice = $invoiceRepository->get($invoiceId);
        
        $this->assertEquals($invoice->getTransactionId(),self::REFERENCE);
    }

    /**
     * @test
     * @covers ::update
     */
    public function update_if_invoice_does_not_exist_throw404()
    {
        $invoice = TestUtils::createInvoice();
        $invoiceId = $invoice->getId();

        $errorCode = 0;
        try {
            $response = $this->invoiceManagement->update($invoiceId+1, self::REFERENCE);
        } catch (WebapiException $e) {
            $errorCode = $e->getHttpCode();
        }
        $this->assertEquals($errorCode,404);
    }
}