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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockStateInterface as StockState;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Class UpdateCartItemTraitTest
 * @package Bolt\Boltpay\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateCartItemTrait
 */
class UpdateCartItemTraitTest extends BoltTestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UpdateCartItemTrait
     */
    private $updateCartItemTrait;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->updateCartItemTrait = $this->objectManager->create(UpdateCart::class);
    }

    /**
     * @test
     */
    public function getProduct_withNoSuchEntityException_returnFalse()
    {
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'getProduct', [100, 1]);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function getProduct_returnProduct()
    {
        $product = TestUtils::getSimpleProduct();
        $productID = $product->getId();
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'getProduct', [$productID, 1]);
        $this->assertEquals($product->getId(), $result->getId());
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     */
    public function verifyItemData_outofStock_returnFalse()
    {
        $itemToAdd = [
            'price' => 4500,
            'currency' => 'USD',
            'product_id' => 100
        ];
        $product = TestUtils::getSimpleProduct();
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'verifyItemData', [$product, $itemToAdd, [], 1]);
        $response = json_decode(
            TestHelper::getProperty($this->updateCartItemTrait, 'response')->getBody(),
            true
        );
        $expectedResponse = [
            'status' => 'failure',
            'errors' => [
                [
                    'code' => BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                    'message' => sprintf('The item [%s] is out of stock.', $itemToAdd['product_id']),
                ]
            ],
        ];
        $this->assertFalse($result);
        $this->assertEquals($expectedResponse, $response);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     */
    public function verifyItemData_priceMismatch_returnFalse()
    {
        $product = TestUtils::getSimpleProduct();
        $itemToAdd = [
            'price' => 4500,
            'currency' => 'USD',
            'product_id' => $product->getId()
        ];
        $stockState = $this->createMock(StockState::class);
        $stockState->method('verifyStock')->willReturn(true);

        TestHelper::setProperty($this->updateCartItemTrait, 'stockState', $stockState);
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'verifyItemData', [$product, $itemToAdd, [], 1]);
        $this->assertFalse($result);
        $response = json_decode(
            TestHelper::getProperty($this->updateCartItemTrait, 'response')->getBody(),
            true
        );
        $expectedResponse = [
            'status' => 'failure',
            'errors' => [
                [
                    'code' => BoltErrorResponse::ERR_ITEM_PRICE_HAS_BEEN_UPDATED,
                    'message' => sprintf('The price of item [%s] does not match product price.', $product->getId()),
                ]
            ],
        ];

        $this->assertEquals($expectedResponse, $response);
        TestUtils::cleanupSharedFixtures([$product]);
    }


    /**
     * @test
     */
    public function verifyItemData_invalidItemQty_returnFalse()
    {
        $product = TestUtils::getSimpleProduct();
        $itemToAdd = [
            'price' => 10000,
            'currency' => 'USD',
            'product_id' => $product->getId(),
            'quantity' => 10,
        ];
        $stockState = $this->createMock(StockState::class);
        $stockState->method('verifyStock')->willReturn(true);


        TestHelper::setProperty($this->updateCartItemTrait, 'stockState', $stockState);
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'verifyItemData', [$product, $itemToAdd, [], 3]);

        $this->assertFalse($result);
        $response = json_decode(
            TestHelper::getProperty($this->updateCartItemTrait, 'response')->getBody(),
            true
        );
        $expectedResponse = [
            'status' => 'failure',
            'errors' => [
                [
                    'code' => BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,
                    'message' => sprintf('The requested qty of item [%s] is not available.', $itemToAdd['product_id']),
                ]
            ],
        ];

        $this->assertEquals($expectedResponse, $response);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     */
    public function verifyItemData_returnTrue()
    {
        $product = TestUtils::getSimpleProduct();
        $itemToAdd = [
            'price' => 10000,
            'currency' => 'USD',
            'product_id' => $product->getId(),
            'quantity' => 1,
        ];
        $stockState = $this->createMock(StockState::class);
        $stockState->method('verifyStock')->willReturn(true);
        $stockState->method('suggestQty')->willReturn(1);

        TestHelper::setProperty($this->updateCartItemTrait, 'stockState', $stockState);
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'verifyItemData', [$product, $itemToAdd, [], 1]);
        $this->assertTrue($result);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     */
    public function addItemToQuote_returnTrue()
    {
        $product = TestUtils::getSimpleProduct();
        $productId = $product->getProduct();
        $itemToAdd = [
            'price' => $product->getPrice(),
            'currency' => 'USD',
            'product_id' => $productId,
            'quantity' => 1,
        ];

        $quote = TestUtils::createQuote();
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'addItemToQuote', [$product, $quote, $itemToAdd, []]);
        $this->assertTrue($result);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     */
    public function addItemToQuote_throwException_returnFalse()
    {
        $itemToAdd = [
            'price' => 4500,
            'currency' => 'USD',
            'product_id' => 100,
            'quantity' => 0,
        ];

        $product = $this->createMock(Product::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('addProduct')->willThrowException(new \Exception());
        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'addItemToQuote', [$product, $quote, $itemToAdd, []]);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function removeItemFromQuote_qtyGreaterThan_returnTrue()
    {
        /** @var Quote $quote */
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 3);
        $quote->save();

        $quoteItem = $quote->getAllVisibleItems()[0];
        $itemToRemove = [
            'price' => 10000,
            'currency' => 'USD',
            'product_id' => $product->getId(),
            'quantity' => 1,
        ];
        $cartItem = [
            'quantity' => 3,
            'quote_item' => $quoteItem,
        ];

        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);
        $this->assertEquals(2, $quoteItem->getQty());
        $this->assertTrue($result);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     */
    public function removeItemFromQuote_qtyEqual_returnTrue()
    {
        /** @var Quote $quote */
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 3);
        $quote->save();

        $quoteItem = $quote->getAllVisibleItems()[0];
        $itemToRemove = [
            'price' => 10000,
            'currency' => 'USD',
            'product_id' => $product->getId(),
            'quantity' => 3,
        ];
        $cartItem = [
            'quantity' => 3,
            'quote_item' => $quoteItem,
            'quote_item_id' => $quoteItem->getId()
        ];

        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);
        $this->assertEquals(0, count($quote->getAllVisibleItems()));
        $this->assertTrue($result);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     *
     */
    public function removeItemFromQuote_qtyLessThan_returnFalse()
    {
        /** @var Quote $quote */
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 3);
        $quote->save();

        $quoteItem = $quote->getAllVisibleItems()[0];
        $itemToRemove = [
            'price' => 10000,
            'currency' => 'USD',
            'product_id' => $product->getId(),
            'quantity' => 4,
        ];
        $cartItem = [
            'quantity' => 3,
            'quote_item' => $quoteItem,
        ];

        $result = TestHelper::invokeMethod($this->updateCartItemTrait, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);

        $this->assertFalse($result);

        $response = json_decode(
            TestHelper::getProperty($this->updateCartItemTrait, 'response')->getBody(),
            true
        );
        $expectedResponse = [
            'status' => 'failure',
            'errors' => [
                [
                    'code' => BoltErrorResponse::ERR_CART_ITEM_REMOVE_FAILED,
                    'message' => sprintf('Could not update the item [%s] with quantity [%s].', $itemToRemove['product_id'], $itemToRemove['quantity'])
                ]
            ],
        ];

        $this->assertEquals($expectedResponse, $response);
        TestUtils::cleanupSharedFixtures([$product]);
    }
}
