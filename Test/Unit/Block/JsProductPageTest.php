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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\JsProductPage as BlockJsProductPage;
use Bolt\Boltpay\Block\JsProductPage;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Helper\Bootstrap;

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
     * @var BlockJsProductPage
     */
    protected $block;

    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->block = $this->objectManager->create(JsProductPage::class);

    }

    /**
     * @test
     */
    public function getProduct()
    {
        $simpleProduct = TestUtils::getSimpleProduct();
        TestHelper::setInaccessibleProperty($this->block, '_product', $simpleProduct);
        $this->assertSame($simpleProduct, $this->block->getProduct());
        TestUtils::cleanupSharedFixtures([$simpleProduct]);
    }

    /**
     * @test
     * @dataProvider providerIsSupportableType
     * @param $typeId
     * @param $expected_result
     * @throws \ReflectionException
     */
    public function isSupportableType($typeId, $expected_result)
    {
        /** @var Product $product */
        $product = $this->objectManager->create(Product::class);
        $product->setTypeId($typeId);
        TestHelper::setInaccessibleProperty($this->block, '_product', $product);
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
     */
    public function isConfigurable()
    {
        $product = TestUtils::createConfigurableProduct();
        TestHelper::setInaccessibleProperty($this->block, '_product', $product);
        $result = $this->block->isConfigurable();
        $this->assertTrue($result);
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     */
    public function isDownloadable()
    {
        $product = TestUtils::createDownloadableProduct();
        TestHelper::setInaccessibleProperty($this->block, '_product', $product);
        $this->assertTrue($this->block->isDownloadable());
        TestUtils::cleanupSharedFixtures([$product]);
    }

    /**
     * @test
     */
    public function isGrouped()
    {
        $product = TestUtils::createGroupProduct();
        TestHelper::setInaccessibleProperty($this->block, '_product', $product);
        $this->assertTrue($this->block->isGrouped());
        TestUtils::cleanupSharedFixtures([$product]);
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
        $store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $configData = [
            [
                'path'    => \Magento\Checkout\Helper\Data::XML_PATH_GUEST_CHECKOUT,
                'value'   => $isGuestCheckoutAllowed,
                'scope'   => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ],
            [
                'path'    => Config::XML_PATH_DISABLE_GUEST_CHECKOUT,
                'value'   => $isGuestCheckoutForDownloadableProductDisabled,
                'scope'   => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $storeId,
            ],

        ];

        TestUtils::setupBoltConfig($configData);

        if ($productType == 'downloadable') {
            $product = TestUtils::createDownloadableProduct();
        }else if($productType == 'configurable') {
            $product = TestUtils::createConfigurableProduct();
        }else {
            $product = TestUtils::createSimpleProduct();
        }
        TestHelper::setInaccessibleProperty($this->block, '_product', $product);

        $result = $this->block->isGuestCheckoutAllowed();
        $this->assertEquals($expectedResult, $result);
        TestUtils::cleanupSharedFixtures([$product]);
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
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_SAVE_HINTS_IN_SECTIONS, $flag);
        $result = $this->block->isSaveHintsInSections();
        $this->assertEquals($flag, $result);
        TestUtils::cleanupFeatureSwitch($featureSwitch);
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
     * @throws \ReflectionException
     */
    public function isBoltProductPage_returnsFalse()
    {
        $this->assertEquals(false, $this->block->isBoltProductPage());
    }

    /**
     * @covers ::isBoltProductPage
     * @test
     */
    public function isBoltProductPage_returnsTrue_ifFlagEnabledParentReturnsTrueAndAttributeFound()
    {
        $contextMock = $this->createMock(\Magento\Framework\View\Element\Template\Context::class);
        $configHelper = $this->createMock(HelperConfig::class);

        $requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getFullActionName'])
            ->getMock();

        $contextMock->method('getRequest')->willReturn($requestMock);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->setMethods(['getExtensionAttributes', 'getStockItem', 'getTypeId', 'getId', 'getTypeInstance', 'getChildrenIds', 'getAttributes'])
            ->getMock();

        $productViewMock = $this->getMockBuilder(ProductView::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProduct'])
            ->getMock();
        $productViewMock->method('getProduct')->willReturn($product);

        $block = $this->getMockBuilder(BlockJsProductPage::class)
            ->setMethods(['configHelper', 'getUrl', 'getBoltPopupErrorMessage'])
            ->setConstructorArgs(
                [
                    $contextMock,
                    $configHelper,
                    $this->createMock(\Magento\Checkout\Model\Session::class),
                    $this->createMock(CartHelper::class),
                    $this->createMock(Bugsnag::class),
                    $productViewMock,
                    $this->createMock(Decider::class),
                    $this->createMock(ProductRepository::class),
                    $this->createMock(SearchCriteriaBuilder::class),
                    $this->createMock(EventsForThirdPartyModules::class),
                    $this->createMock(HttpContext::class),
                ]
            )
            ->getMock();

        $configHelper->expects(static::once())->method('getSelectProductPageCheckoutFlag')->willReturn(true);
        $configHelper->expects(static::once())->method('getProductPageCheckoutFlag')->willReturn(true);
        $requestMock->expects(static::once())->method('getFullActionName')->willReturn('catalog_product_view');
        $attr1 = $this->createMock(AbstractAttribute::class);
        $attr1->expects(static::once())->method('getName')->willReturn('attr1');
        $attr2 = $this->createMock(AbstractAttribute::class);
        $attr2->expects(static::once())->method('getName')->willReturn('bolt_ppc');
        $product->expects(static::once())->method('getAttributes')->willReturn([$attr1, $attr2]);
        $this->assertEquals(true, $block->isBoltProductPage());
    }
}
