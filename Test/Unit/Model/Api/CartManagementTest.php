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

use Bolt\Boltpay\Model\Api\CartManagement;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class CartInterfaceTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\CartManagement
 */
class CartInterfaceTest extends BoltTestCase
{
    /** array of objects we need to delete after test */
    private $objectsToClean;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectsToClean = [];
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cartManagement = $this->objectManager->create(CartManagement::class);
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures($this->objectsToClean);
    }

    private function forceMaskedQuoteIDCreation($quoteID) {
        $checkoutSession = $this->objectManager->get(\Magento\Checkout\Model\Session::class);
        $checkoutSession->setQuoteId($quoteID);
        $checkoutSession->getQuote();
    }

    private function getMaskedQuoteID($quoteID) {
        $quoteIdMaskFactory = $this->objectManager->get(\Magento\Quote\Model\QuoteIdMaskFactory::class);
        $quoteIdMask = $quoteIdMaskFactory->create();
        $maskedQuoteID = $quoteIdMask->load($quoteID, 'quote_id')->getMaskedId();
        return $maskedQuoteID;
    }

    /**
     * @test
     * @covers ::getMaskedId
     */
    public function getMaskedId_happyPath()
    {
        $quote = TestUtils::createQuote();

        $this->forceMaskedQuoteIDCreation($quote->getID());

        $response = $this->cartManagement->getMaskedId($quote->getID());
        
        $this->assertEquals($response->getMaskedQuoteID(),$this->getMaskedQuoteID($quote->getID()));
    }

    /**
     * @test
     * @covers ::getMaskedId
     */
    public function getMaskedId_maskedQuoteIDDoesNotCreated_throw404()
    {
        $quote = TestUtils::createQuote();

        $errorCode = 0;
        try {
            $response = $this->cartManagement->getMaskedId($quote->getID());
        } catch (WebapiException $e) {
            $errorCode = $e->getHttpCode();
        }
        $this->assertEquals($errorCode,404);
    }

    private function createCustomer()
    {
        $addressInfo = TestUtils::createSampleAddress();
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        $customer = TestUtils::createCustomer($storeId, $websiteId, $addressInfo);

        return $customer;
    }


    /**
     * @test
     * @covers ::getMaskedId
     */
    public function getMaskedId_quoteCreatedByLoggedInUser_throw404()
    {
        $quote = TestUtils::createQuote();

        $customer = $this->createCustomer();

        $session = Bootstrap::getObjectManager()->get(
            \Magento\Customer\Model\Session::class
        );
        $session->setCustomer($customer);

        // magento doesnot create masked quote ID for logged in users
        $this->forceMaskedQuoteIDCreation($quote->getID());

        $errorCode = 0;
        try {
            $response = $this->cartManagement->getMaskedId($quote->getID());
        } catch (WebapiException $e) {
            $errorCode = $e->getHttpCode();
        }
        $this->assertEquals($errorCode,404);
    }

    /**
     * @test
     * @covers ::getMaskedId
     */
    public function getMaskedId_wrongQuoteID_throw404()
    {
        $errorCode = 0;
        try {
            $response = $this->cartManagement->getMaskedId(1000000);
        } catch (WebapiException $e) {
            $errorCode = $e->getHttpCode();
        }
        $this->assertEquals($errorCode,404);
    }
}