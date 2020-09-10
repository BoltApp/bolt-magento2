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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Aheadworks;

use Bolt\Boltpay\Test\Unit\AheadworksGiftcardRepositoryMock;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use \Bolt\Boltpay\ThirdPartyModules\Aheadworks\Giftcard;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Model\Service\OrderService;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class GiftcardTest
 * @package Bolt\Boltpay\Test\Unit\ThirdPartyModules\Aheadworks
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Aheadworks\Giftcard
 */
class GiftcardTest extends TestCase
{
    const GIFT_CARD_CODE = 'XXX';
    const STORE_ID = 1;
    const PARENT_QUOTE_ID = 1;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var Giftcard
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->orderService = $this->createMock(OrderService::class);
        $this->bugsnagHelper = $this->createPartialMock(Bugsnag::class, [
            'notifyException'
        ]);
        $this->currentMock = (new ObjectManager($this))->getObject(
            Giftcard::class,
            [
                'orderService' => $this->orderService,
                'bugsnagHelper' => $this->bugsnagHelper
            ]
        );
    }

    /**
     * @test
     * @covers ::loadGiftcard
     *
     */
    public function loadGiftcard_success()
    {
        $aheadworksGiftcardRepository = $this->createPartialMock(AheadworksGiftcardRepositoryMock::class, ['getByCode']);
        $aheadworksGiftcardRepository->expects(self::once())->method('getByCode')->with(self::GIFT_CARD_CODE, self::STORE_ID);
        $this->currentMock->loadGiftcard(null, $aheadworksGiftcardRepository, self::GIFT_CARD_CODE, self::STORE_ID);
    }

    /**
     * @test
     * @covers ::loadGiftcard
     *
     */
    public function loadGiftcard_withException()
    {
        $aheadworksGiftcardRepository = $this->createPartialMock(AheadworksGiftcardRepositoryMock::class, ['getByCode']);
        $aheadworksGiftcardRepository->expects(self::once())->method('getByCode')->with(self::GIFT_CARD_CODE, self::STORE_ID)->willThrowException(new LocalizedException(__('message')));;
        $this->assertNull($this->currentMock->loadGiftcard(null, $aheadworksGiftcardRepository, self::GIFT_CARD_CODE, self::STORE_ID));
    }

    /**
     * @test
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_success()
    {
        $aheadworksGiftcardManagementMock = $this->createPartialMock(AheadworksGiftcardRepositoryMock::class, ['get', 'getGiftcardCode', 'getGiftcardAmount', 'getGiftcardBalance']);
        $quoteMock = $this->createPartialMock(Quote::class, ['getData', 'getQuoteCurrencyCode']);
        $quoteMock->method('getData')->with('bolt_parent_quote_id')->willReturn(self::PARENT_QUOTE_ID);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn('USD');
        $aheadworksGiftcardManagementMock->method('get')->with(self::PARENT_QUOTE_ID, false)->willReturn([$aheadworksGiftcardManagementMock]);
        $aheadworksGiftcardManagementMock->method('getGiftcardCode')->willReturn(self::GIFT_CARD_CODE);
        $aheadworksGiftcardManagementMock->method('getGiftcardBalance')->willReturn(100);
        $aheadworksGiftcardManagementMock->method('getGiftcardAmount')->willReturn(100);
        $result = $this->currentMock->collectDiscounts([[], 0, 0], $aheadworksGiftcardManagementMock, $quoteMock);

        $this->assertEquals([
            [['amount' => 10000, 'description' => 'Gift Card (XXX)', 'type' => 'fixed_amount']],
            -10000,
            0
        ], $result);
    }

    /**
     * @test
     * @covers ::collectDiscounts
     */
    public function collectDiscounts_withException()
    {
        $aheadworksGiftcardManagementMock = $this->createPartialMock(AheadworksGiftcardRepositoryMock::class, ['get', 'getGiftcardCode', 'getGiftcardAmount', 'getGiftcardBalance']);
        $quoteMock = $this->createPartialMock(Quote::class, ['getData', 'getQuoteCurrencyCode']);
        $quoteMock->method('getData')->with('bolt_parent_quote_id')->willReturn(self::PARENT_QUOTE_ID);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn('USD');
        $aheadworksGiftcardManagementMock->method('get')->with(self::PARENT_QUOTE_ID, false)->willThrowException(new \Exception('message'));
        $this->bugsnagHelper->expects(self::once())->method('notifyException')->with(new \Exception('message'));
        $this->currentMock->collectDiscounts([[], 0, 0], $aheadworksGiftcardManagementMock, $quoteMock);
    }

    /**
     * @test
     * @covers ::collectDiscounts
     */
    public function beforeDeleteOrder()
    {
        $aheadworksGiftcardOrderServicePlugin = $this->createPartialMock(AheadworksGiftcardRepositoryMock::class, ['aroundCancel']);
        $aheadworksGiftcardOrderServicePlugin->expects(self::once())->method('aroundCancel')->withAnyParameters();
        $order = $this->createMock(Order::class);
        $this->currentMock->beforeDeleteOrder($aheadworksGiftcardOrderServicePlugin, $order);
    }
}