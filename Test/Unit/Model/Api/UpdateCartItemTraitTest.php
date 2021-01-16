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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\CatalogInventory\Api\StockStateInterface as StockState;
use Magento\Catalog\Api\Data\ProductInterface;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Model\Api\UpdateCartItemTrait;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * Class UpdateCartItemTraitTest
 * @coversDefaultClass \Bolt\Boltpay\Controller\UpdateCartItemTrait
 */
class UpdateCartItemTraitTest extends BoltTestCase
{
    /**
     * @var ProductRepository|MockObject
     */
    private $productRepository;
    
    /**
     * @var StockState|MockObject
     */
    private $stockState;
    
    /**
     * @var UpdateCartItemTrait
     */
    private $currentMock;


    public function setUpInternal()
    {            
        $this->currentMock = $this->getMockBuilder(UpdateCartItemTrait::class)
            ->setMethods(['sendErrorResponse'])
            ->disableOriginalConstructor()
            ->getMockForTrait();
            
        $this->productRepository = $this->createMock(ProductRepository::class);

        $this->stockState = $this->createMock(StockState::class);
        
        TestHelper::setProperty($this->currentMock, 'productRepository', $this->productRepository);
        TestHelper::setProperty($this->currentMock, 'stockState', $this->stockState);
    }
    
    /**
     * @test
     * 
     */
    public function getProduct_withNoSuchEntityException_returnFalse()
    {
        $this->productRepository->expects(static::once())->method('getById')
            ->with(100, false, 1, true)->willThrowException(new NoSuchEntityException());
            
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_PRODUCT_DOES_NOT_EXIST,'The item [100] does not exist.',422);
            
        $result = TestHelper::invokeMethod($this->currentMock, 'getProduct', [100,1]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function getProduct_withException_returnFalse()
    {
        $exception = new \Exception('General exception');
        $this->productRepository->expects(static::once())->method('getById')
            ->with(100, false, 1, true)->willThrowException($exception);
            
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,'General exception',422);
            
        $result = TestHelper::invokeMethod($this->currentMock, 'getProduct', [100,1]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function getProduct_returnProduct()
    {
        $product = $this->createMock(ProductInterface::class);
            
        $this->productRepository->expects(static::once())->method('getById')
            ->with(100, false, 1, true)->willReturn($product);
            
        $result = TestHelper::invokeMethod($this->currentMock, 'getProduct', [100,1]);
        
        $this->assertEquals($product, $result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyItemData_priceMismatch_returnFalse()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100
        ];
        
        $product = $this->createMock(ProductInterface::class);
        $product->expects(static::once())->method('getPrice')->willReturn(41);
        
        $this->stockState->expects(static::once())->method('verifyStock')
             ->with(100)->willReturn(true);
            
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(6604,'The price of item [100] does not match product price.',422);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyItemData', [$product, $itemToAdd, [], 1]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyItemData_outofStock_returnFalse()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100
        ];
        
        $product = $this->createMock(ProductInterface::class);
        
        $this->stockState->expects(static::once())->method('verifyStock')
             ->with(100)->willReturn(false);
            
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,'The item [100] is out of stock.',422);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyItemData', [$product, $itemToAdd, [], 1]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyItemData_invalidItemQty_returnFalse()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 10,
        ];
        
        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getWebsiteId')->willReturn(1);
        
        $product = $this->getMockBuilder(\Magento\Catalog\Model\Product::class)
            ->setMethods(['getPrice', 'getStore', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects(static::once())->method('getId')->willReturn(100);
        $product->expects(static::once())->method('getPrice')->willReturn(45);
        $product->expects(static::once())->method('getStore')->willReturn($storeMock);
        
        $this->stockState->expects(static::once())->method('suggestQty')
             ->with(100, 10, 1)->willReturn(8);
        $this->stockState->expects(static::once())->method('verifyStock')
             ->with(100)->willReturn(true);     
             
        
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_ITEM_OUT_OF_STOCK,'The requested qty of item [100] is not available.',422);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyItemData', [$product, $itemToAdd, [], 3]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function verifyItemData_returnTrue()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 1,
        ];
        
        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getWebsiteId')->willReturn(1);
        
        $product = $this->getMockBuilder(\Magento\Catalog\Model\Product::class)
            ->setMethods(['getPrice', 'getStore', 'getId'])
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects(static::once())->method('getId')->willReturn(100);
        $product->expects(static::once())->method('getPrice')->willReturn(45);
        $product->expects(static::once())->method('getStore')->willReturn($storeMock);
        
        $this->stockState->expects(static::once())->method('verifyStock')
             ->with(100)->willReturn(true);
        
        $this->stockState->expects(static::once())->method('suggestQty')
             ->with(100, 1, 1)->willReturn(1);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'verifyItemData', [$product, $itemToAdd, [], 3]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     * 
     */
    public function addItemToQuote_returnTrue()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 1,
        ];
        
        $product = $this->createMock(ProductInterface::class);
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'addProduct',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quote->expects(static::once())->method('addProduct')->with($product,1);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'addItemToQuote', [$product, $quote, $itemToAdd, []]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     * 
     */
    public function addItemToQuote_throwException_returnFalse()
    {
        $itemToAdd = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 1,
        ];
        
        $product = $this->createMock(ProductInterface::class);
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'addProduct',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $exception = new \Exception('General exception');
        $quote->expects(static::once())->method('addProduct')
              ->with($product,1)->willThrowException($exception);
        
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CART_ITEM_ADD_FAILED,'Fail to add item [100]. Reason: [General exception].',422);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'addItemToQuote', [$product, $quote, $itemToAdd, []]);
        
        $this->assertFalse($result);
    }
    
    /**
     * @test
     * 
     */
    public function removeItemFromQuote_qtyGreaterThan_returnTrue()
    {
        $itemToRemove = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 1,
        ];
        
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['setQty', 'save'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('setQty')->willReturnSelf();
        $quoteItem->method('save')->willReturnSelf();
        
        $cartItem = [
            'reference'    => 100,
            'name'         => 'Test Product',
            'total_amount' => 56,
            'unit_price'   => 56,
            'quantity'     => 2,
            'sku'          => 'test-product-101',
            'type'         => 'physical',
            'description'  => '',
            'quote_item_id'=> 2,
            'quote_item'   => $quoteItem,
        ];
        
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                []
            )
            ->disableOriginalConstructor()
            ->getMock();
        
        $result = TestHelper::invokeMethod($this->currentMock, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     * 
     */
    public function removeItemFromQuote_qtyEqual_returnTrue()
    {
        $itemToRemove = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 1,
        ];
        
        $cartItem = [
            'reference'    => 100,
            'name'         => 'Test Product',
            'total_amount' => 56,
            'unit_price'   => 56,
            'quantity'     => 1,
            'sku'          => 'test-product-101',
            'type'         => 'physical',
            'description'  => '',
            'quote_item_id'=> 5,
        ];
        
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods(
                [
                    'removeItem',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $quote->expects(static::once())->method('removeItem')->with(5);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);
        
        $this->assertTrue($result);
    }
    
    /**
     * @test
     * 
     */
    public function removeItemFromQuote_qtyLessThan_returnFalse()
    {
        $itemToRemove = [
            'price'      => 4500,
            'currency'   => 'USD',
            'product_id' => 100,
            'quantity'   => 3,
        ];
        
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods(['setQty', 'save'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('setQty')->willReturnSelf();
        $quoteItem->method('save')->willReturnSelf();
        
        $cartItem = [
            'reference'    => 100,
            'name'         => 'Test Product',
            'total_amount' => 56,
            'unit_price'   => 56,
            'quantity'     => 1,
            'sku'          => 'test-product-101',
            'type'         => 'physical',
            'description'  => '',
            'quote_item_id'=> 5,
            'quote_item'   => $quoteItem
        ];
        
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->currentMock->expects(self::once())->method('sendErrorResponse')
            ->with(BoltErrorResponse::ERR_CART_ITEM_REMOVE_FAILED,'Could not update the item [100] with quantity [3].',422);
        
        $result = TestHelper::invokeMethod($this->currentMock, 'removeItemFromQuote', [$cartItem, $itemToRemove, $quote]);
        
        $this->assertFalse($result);
    }

}
