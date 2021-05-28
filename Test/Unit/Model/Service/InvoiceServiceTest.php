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

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Model\Service\InvoiceService;

/**
 * Class InvoiceServiceTest
 * @package Bolt\Boltpay\Test\Unit\Model
 * @coversDefaultClass \Bolt\Boltpay\Model\Service\InvoiceService
 */
class InvoiceServiceTest extends BoltTestCase
{
    const AMOUNT = 20;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var InvoiceService
     */
    private $currentMock;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->currentMock = $this->objectManager->create(InvoiceService::class);
    }

    /**
     * @covers ::prepareInvoiceWithoutItems
     * @test
     */
    public function prepareInvoiceWithoutItems()
    {
        $order = TestUtils::createDumpyOrder();
        $invoice = $this->currentMock->prepareInvoiceWithoutItems($order, self::AMOUNT);
        self::assertEquals(self::AMOUNT, $invoice->getGrandTotal());
        self::assertEquals(self::AMOUNT, $invoice->getSubtotal());
        self::assertEquals(self::AMOUNT, $invoice->getBaseGrandTotal());
        self::assertEquals(self::AMOUNT, $invoice->getBaseSubtotal());
        TestUtils::cleanupSharedFixtures([$order]);
    }
}
