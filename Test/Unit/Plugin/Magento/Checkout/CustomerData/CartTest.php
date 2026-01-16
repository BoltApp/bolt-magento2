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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Checkout\CustomerData;

use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Plugin\Magento\Checkout\CustomerData\Cart;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Checkout\CustomerData\Cart as CustomerDataCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Serialize\SerializerInterface as Serializer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Class CartTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\Checkout\CustomerData
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Checkout\CustomerData\Cart
 */
class CartTest extends BoltTestCase
{
    /**
     * @var Cart
     */
    private $cartPlugin;

    /**
     * @var CheckoutSession|\PHPUnit\Framework\MockObject\MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var BoltHelperCart|\PHPUnit\Framework\MockObject\MockObject
     */
    private $boltHelperCartMock;

    /**
     * @var Bugsnag|\PHPUnit\Framework\MockObject\MockObject
     */
    private $bugsnagMock;

    /**
     * @var Decider|\PHPUnit\Framework\MockObject\MockObject
     */
    private $featureSwitchesMock;

    /**
     * @var ConfigHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configHelperMock;

    /**
     * @var DataObjectFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $dataObjectFactoryMock;

    /**
     * @var ApiHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $apiHelperMock;

    /**
     * @var Serializer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $serializerMock;

    /**
     * @var CacheInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $cacheMock;

    /**
     * @var CustomerDataCart|\PHPUnit\Framework\MockObject\MockObject
     */
    private $subjectMock;

    /**
     * @var Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteMock;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->boltHelperCartMock = $this->createMock(BoltHelperCart::class);
        $this->bugsnagMock = $this->createMock(Bugsnag::class);
        $this->featureSwitchesMock = $this->createPartialMock(
            Decider::class,
            ['isEnabledFetchCartViaApi', 'isEnabledPreFetchCartViaApi']
        );
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->dataObjectFactoryMock = $this->createMock(DataObjectFactory::class);
        $this->apiHelperMock = $this->createMock(ApiHelper::class);
        $this->serializerMock = $this->createMock(Serializer::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->subjectMock = $this->createMock(CustomerDataCart::class);
        $this->quoteMock = $this->createMock(Quote::class);

        $this->cartPlugin = new Cart(
            $this->checkoutSessionMock,
            $this->boltHelperCartMock,
            $this->bugsnagMock,
            $this->featureSwitchesMock,
            $this->configHelperMock,
            $this->dataObjectFactoryMock,
            $this->apiHelperMock,
            $this->serializerMock,
            $this->cacheMock
        );
    }

    /**
     * @test
     * @covers ::preFetchCart
     * that preFetchCart skips pre-fetch request when feature switch is disabled
     */
    public function preFetchCart_featureDisabled_skipPreFetch()
    {
        $this->featureSwitchesMock->method('isEnabledPreFetchCartViaApi')->willReturn(false);
        $this->quoteMock->method('getId')->willReturn(123);
        $this->quoteMock->method('getAllItems')->willReturn([
            $this->createMock(QuoteItem::class)
        ]);

        // Cache should never be checked if feature is disabled
        $this->cacheMock->expects(self::never())->method('load');
        $this->apiHelperMock->expects(self::never())->method('sendRequest');

        TestHelper::invokeMethod(
            $this->cartPlugin,
            'preFetchCart',
            [$this->quoteMock, ['test' => 'data']]
        );
    }

    /**
     * @test
     * @covers ::preFetchCart
     * that preFetchCart skips pre-fetch request when quote has no ID
     */
    public function preFetchCart_quoteHasNoId_skipPreFetch()
    {
        $this->featureSwitchesMock->method('isEnabledPreFetchCartViaApi')->willReturn(true);
        $this->quoteMock->method('getId')->willReturn(null);
        $this->quoteMock->method('getAllItems')->willReturn([
            $this->createMock(QuoteItem::class)
        ]);

        // Cache should never be checked if quote has no ID
        $this->cacheMock->expects(self::never())->method('load');
        $this->apiHelperMock->expects(self::never())->method('sendRequest');

        TestHelper::invokeMethod(
            $this->cartPlugin,
            'preFetchCart',
            [$this->quoteMock, ['test' => 'data']]
        );
    }

    /**
     * @test
     * @covers ::preFetchCart
     * that preFetchCart skips pre-fetch request when quote has no items
     */
    public function preFetchCart_quoteHasNoItems_skipPreFetch()
    {
        $this->featureSwitchesMock->method('isEnabledPreFetchCartViaApi')->willReturn(true);
        $this->quoteMock->method('getId')->willReturn(123);
        $this->quoteMock->method('getAllItems')->willReturn([]);

        // Cache should never be checked if quote has no items
        $this->cacheMock->expects(self::never())->method('load');
        $this->apiHelperMock->expects(self::never())->method('sendRequest');

        TestHelper::invokeMethod(
            $this->cartPlugin,
            'preFetchCart',
            [$this->quoteMock, ['test' => 'data']]
        );
    }

    /**
     * @test
     * @covers ::preFetchCart
     * @dataProvider dataProvider_preFetchCart_skipConditions
     *
     * @param bool $featureEnabled
     * @param int|null $quoteId
     * @param array $quoteItems
     * @param bool $expectSkip
     */
    public function preFetchCart_variousConditions_skipOrProceed(
        bool $featureEnabled,
        $quoteId,
        array $quoteItems,
        bool $expectSkip
    ) {
        $this->featureSwitchesMock->method('isEnabledPreFetchCartViaApi')->willReturn($featureEnabled);
        $this->quoteMock->method('getId')->willReturn($quoteId);
        $this->quoteMock->method('getAllItems')->willReturn($quoteItems);

        if ($expectSkip) {
            // Cache should never be checked if skip conditions are met
            $this->cacheMock->expects(self::never())->method('load');
            $this->apiHelperMock->expects(self::never())->method('sendRequest');
        } else {
            // If not skipped, cache should be checked
            $this->serializerMock->method('serialize')->willReturn('serialized_data');
            $this->cacheMock->expects(self::once())->method('load')->willReturn('cached_value');
        }

        TestHelper::invokeMethod(
            $this->cartPlugin,
            'preFetchCart',
            [$this->quoteMock, ['test' => 'data']]
        );
    }

    /**
     * Data provider for preFetchCart_variousConditions_skipOrProceed
     *
     * @return array[] containing featureEnabled, quoteId, quoteItems, expectSkip
     */
    public function dataProvider_preFetchCart_skipConditions(): array
    {
        $mockQuoteItem = $this->createMock(QuoteItem::class);

        return [
            'Feature disabled - skip' => [
                'featureEnabled' => false,
                'quoteId' => 123,
                'quoteItems' => [$mockQuoteItem],
                'expectSkip' => true,
            ],
            'Quote has no ID - skip' => [
                'featureEnabled' => true,
                'quoteId' => null,
                'quoteItems' => [$mockQuoteItem],
                'expectSkip' => true,
            ],
            'Quote has no items - skip' => [
                'featureEnabled' => true,
                'quoteId' => 123,
                'quoteItems' => [],
                'expectSkip' => true,
            ],
            'All conditions met - proceed' => [
                'featureEnabled' => true,
                'quoteId' => 123,
                'quoteItems' => [$mockQuoteItem],
                'expectSkip' => false,
            ],
        ];
    }
}
