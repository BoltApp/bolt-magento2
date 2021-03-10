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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\JsProductPage as BlockJsProductPage;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;

/**
 * Class JsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 * @coversDefaultClass \Bolt\Boltpay\Block\JsProductPage
 */
class JsProductPageTest extends BoltTestCase
{
    const CURRENCY_CODE = 'USD';
    const PRODUCT_ID = '1';

    /**
     * @var HelperConfig
     */
    protected $configHelper;

    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    protected $helperContextMock;

    /**
     * @var \Magento\Framework\View\Element\Template\Context
     */
    protected $contextMock;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSessionMock;

    /**
     * @var BlockJsProductPage
     */
    protected $block;

    /**
     * @var HttpContext|MockObject
     */
    protected $httpContextMock;

    /**
     * @var Http
     */
    private $requestMock;

    /**
     * @var CartHelper
     */
    private $cartHelperMock;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelperMock;

    /**
     * @var ProductView
     */
    private $productViewMock;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var MockObject|EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @test
     */
    public function getProduct()
    {
        $result = $this->block->getProduct();
        $this->assertSame($this->product, $result);
    }

    /**
     * @test
     * @dataProvider providerIsSupportableType
     *
     * @param mixed $typeId
     * @param mixed $expected_result
     */
    public function isSupportableType($typeId, $expected_result)
    {
        $this->product->method('getTypeId')->willReturn($typeId);
        $result = $this->block->isSupportableType();
        $this->assertEquals($expected_result, $result);
    }

    public function providerIsSupportableType()
    {
        return [
            ['simple', true],
            ['grouped', true],
            ['configurable', true],
            ['virtual', true],
            ['bundle', true],
            ['downloadable', true]
        ];
    }

    /**
     * @test
     * @dataProvider providerIsConfigurable
     *
     * @param mixed $typeId
     * @param mixed $expected_result
     */
    public function isConfigurable($typeId, $expected_result)
    {
        $this->product->method('getTypeId')->willReturn($typeId);
        $result = $this->block->isConfigurable();
        $this->assertEquals($expected_result, $result);
    }

    public function providerIsConfigurable()
    {
        return [
            ['simple', false],
            ['grouped', false],
            ['configurable', true],
            ['virtual', false],
            ['bundle', false],
            ['downloadable', false]
        ];
    }

    /**
     * @test
     * @dataProvider providerIsDownloadable
     *
     * @param $typeId
     * @param $expectedResult
     */
    public function isDownloadable($typeId, $expectedResult)
    {
        $this->product->method('getTypeId')->willReturn($typeId);
        $result = $this->block->isDownloadable();
        $this->assertEquals($expectedResult, $result);
    }

    public function providerIsDownloadable()
    {
        return [
            ['simple', false],
            ['grouped', false],
            ['configurable', false],
            ['virtual', false],
            ['bundle', false],
            ['downloadable', true]
        ];
    }

    /**
     * @test
     * @dataProvider providerIsGrouped
     *
     * @param $typeId
     * @param $expectedResult
     */
    public function isGrouped($typeId, $expectedResult)
    {
        $this->product->method('getTypeId')->willReturn($typeId);
        $result = $this->block->isGrouped();
        $this->assertEquals($expectedResult, $result);
    }

    public function providerIsGrouped()
    {
        return [
            ['simple', false],
            ['grouped', true],
            ['configurable', false],
            ['virtual', false],
            ['bundle', false],
            ['downloadable', false]
        ];
    }

    /**
     * @covers ::getGroupedProductChildren
     * @test
     */
    public function getGroupedProductChildren()
    {
        $this->product->expects(self::once())->method('getTypeInstance')->willReturnSelf();
        $this->product->expects(self::once())->method('getId')->willReturn(self::PRODUCT_ID);
        $this->product->expects(self::once())->method('getChildrenIds')->with(self::PRODUCT_ID)->willReturn([2, 3]);

        $this->searchCriteriaBuilder->expects(self::once())->method('addFilter')->with('entity_id', [2, 3], 'in')->willReturnSelf();
        $searchCriteriaBuilderInterFace = $this->createMock(\Magento\Framework\Api\SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->expects(self::once())->method('create')->willReturn($searchCriteriaBuilderInterFace);

        $this->productRepository->expects(self::once())->method('getList')->with($searchCriteriaBuilderInterFace)->willReturnSelf();
        $this->productRepository->expects(self::once())->method('getItems')->willReturn([$this->product]);

        $this->assertEquals([$this->product], $this->block->getGroupedProductChildren());
    }

    /**
     * @test
     * @dataProvider providerIsGuestCheckoutAllowed
     *
     * @param $isGuestCheckoutAllowed
     * @param $isGuestCheckoutForDownloadableProductDisabled
     * @param $expectedResult
     * @param $productType
     */
    public function isGuestCheckoutAllowed($isGuestCheckoutAllowed, $isGuestCheckoutForDownloadableProductDisabled, $expectedResult, $productType = 'simple')
    {
        $this->configHelper->method('isGuestCheckoutAllowed')
            ->willReturn($isGuestCheckoutAllowed);
        $this->configHelper->method('isGuestCheckoutForDownloadableProductDisabled')
            ->willReturn($isGuestCheckoutForDownloadableProductDisabled);
        $this->product->method('getTypeId')->willReturn($productType);
        $result = $this->block->isGuestCheckoutAllowed();
        $this->assertEquals($expectedResult, $result);
    }

    public function providerIsGuestCheckoutAllowed()
    {
        return [
            [true, true, 0, 'downloadable'],
            [true, true, 1],
            [true, false, 1, 'configurable'],
        ];
    }

    /**
     * @test
     */
    public function getStoreCurrencyCode()
    {
        $result = $this->block->getStoreCurrencyCode();
        $this->assertEquals(self::CURRENCY_CODE, $result);
    }

    /**
     * @test
     * @dataProvider providerIsSaveHintsInSections
     *
     * @param mixed $flag
     */
    public function isSaveHintsInSections($flag)
    {
        $this->featureSwitches->method('isSaveHintsInSections')->willReturn($flag);
        $result = $this->block->isSaveHintsInSections();
        $this->assertEquals($flag, $result);
    }

    public function providerIsSaveHintsInSections()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @test
     */
    public function isBoltProductPage_returnsFalse_ifFlagNotEnabledAndParentReturnsFalse()
    {
        $this->configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(false);
        $this->configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(false);
        $this->assertEquals(false, $this->block->isBoltProductPage());
    }

    /**
     * @test
     */
    public function isBoltProductPage_returnsTrue_ifFlagNotEnabledAndParentReturnsTrue()
    {
        $this->configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(false);
        $this->configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(true);
        $this->requestMock->expects(static::once())->method('getFullActionName')->willReturn('catalog_product_view');
        $this->assertEquals(true, $this->block->isBoltProductPage());
    }

    /**
     * @test
     */
    public function isBoltProductPage_returnsFalse_ifFlagEnabledButParentReturnsFalse()
    {
        $this->configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(true);
        $this->configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(false);
        $this->assertEquals(false, $this->block->isBoltProductPage());
    }

    /**
     * @test
     */
    public function isBoltProductPage_returnsTrue_ifFlagEnabledParentReturnsTrueAndAttributeFound()
    {
        $this->configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(true);
        $this->configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(true);
        $this->requestMock->expects(static::once())->method('getFullActionName')->willReturn('catalog_product_view');
        $attr1 = $this->createMock(AbstractAttribute::class);
        $attr1->expects(static::once())->method('getName')->willReturn('attr1');
        $attr2 = $this->createMock(AbstractAttribute::class);
        $attr2->expects(static::once())->method('getName')->willReturn('bolt_ppc');
        $this->product->expects(static::once())->method('getAttributes')->willReturn([$attr1, $attr2]);
        $this->assertEquals(true, $this->block->isBoltProductPage());
    }

    /**
     * @test
     */
    public function isBoltProductPage_returnsFalse_ifFlagEnabledParentReturnsTrueButAttributeNotFound()
    {
        $this->configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(true);
        $this->configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(true);
        $this->requestMock->expects(static::once())->method('getFullActionName')->willReturn('catalog_product_view');
        $attr1 = $this->createMock(AbstractAttribute::class);
        $attr1->expects(static::once())->method('getName')->willReturn('attr1');
        $attr2 = $this->createMock(AbstractAttribute::class);
        $attr2->expects(static::once())->method('getName')->willReturn('attr2');
        $this->product->expects(static::once())->method('getAttributes')->willReturn([$attr1, $attr2]);
        $this->assertEquals(false, $this->block->isBoltProductPage());
    }

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->helperContextMock = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->contextMock = $this->createMock(\Magento\Framework\View\Element\Template\Context::class);

        $this->checkoutSessionMock = $this->getMockBuilder(\Magento\Checkout\Model\Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote', 'getBoltInitiateCheckout', 'unsBoltInitiateCheckout'])
            ->getMock();

        $this->eventsForThirdPartyModules = $this->createPartialMock(EventsForThirdPartyModules::class, ['runFilter']);
        $this->eventsForThirdPartyModules->method('runFilter')->will($this->returnArgument(1));

        $this->httpContextMock = $this->createMock(HttpContext::class);

        $this->configHelper = $this->createMock(HelperConfig::class);
        $this->cartHelperMock = $this->createMock(CartHelper::class);
        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);
        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getFullActionName'])
            ->getMock();

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $storeManager = $this->getMockForAbstractClass(\Magento\Store\Model\StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->contextMock->method('getStoreManager')->willReturn($storeManager);

        $this->product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->setMethods(['getExtensionAttributes', 'getStockItem', 'getTypeId', 'getId', 'getTypeInstance', 'getChildrenIds', 'getAttributes'])
            ->getMock();

        $this->productViewMock = $this->getMockBuilder(ProductView::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProduct'])
            ->getMock();
        $this->productViewMock->method('getProduct')
            ->willReturn($this->product);
        $this->featureSwitches = $this->createMock(Decider::class);
        $this->productRepository = $this->createPartialMock(ProductRepository::class, [
            'getList',
            'getItems'
        ]);
        $this->searchCriteriaBuilder = $this->createPartialMock(SearchCriteriaBuilder::class, ['addFilter', 'create']);

        $this->block = $this->getMockBuilder(BlockJsProductPage::class)
            ->setMethods(['configHelper', 'getUrl', 'getBoltPopupErrorMessage'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelper,
                    $this->checkoutSessionMock,
                    $this->cartHelperMock,
                    $this->bugsnagHelperMock,
                    $this->productViewMock,
                    $this->featureSwitches,
                    $this->productRepository,
                    $this->searchCriteriaBuilder,
                    $this->eventsForThirdPartyModules,
                    $this->httpContextMock,
                ]
            )
            ->getMock();
    }
}
