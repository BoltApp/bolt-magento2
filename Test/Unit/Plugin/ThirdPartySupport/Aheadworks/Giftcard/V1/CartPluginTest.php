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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1;

use Aheadworks\Giftcard\Api\GiftcardCartManagementInterface;
use Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\CartPlugin;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\CartPlugin
 */
class CartPluginTest extends TestCase
{
    /** @var int Test parent quote ID */
    const PARENT_QUOTE_ID = 456;

    /** @var string Default quote currency code */
    const CURRENCY_CODE = 'USD';

    /** @var array Default test result of {@see \Bolt\Boltpay\Helper\Cart::collectDiscounts} */
    const TEST_DEFAULT_RESULT = [[], 12345, 0];

    /**
     * @var \Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext|MockObject used to provide common dependencies
     */
    private $contextMock;

    /**
     * @var \Bolt\Boltpay\Model\ThirdPartyModuleFactory|MockObject mocked instance of the Aheadworks Giftcard Management
     */
    private $giftcardCartManagementFactoryMock;

    /**
     * @var CartPlugin|MockObject mocked instance of the tested class
     */
    private $currentMock;

    /**
     * @var \Bolt\Boltpay\Helper\Cart|MockObject mocked instance of the plugged class
     */
    private $subjectMock;

    /**
     * @var \Magento\Quote\Model\Quote|MockObject mocked instance of the Magento Quote model
     */
    private $immutableQuoteMock;

    /**
     * @var GiftcardCartManagementInterface|MockObject mocked instance of Aheadworks Giftcard Cart Manaagement
     */
    private $aheadworksGiftcardManagementMock;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->contextMock = $this->createMock(\Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext::class);
        $this->giftcardCartManagementFactoryMock = $this->createMock(
            \Bolt\Boltpay\Model\ThirdPartyModuleFactory::class
        );
        $this->subjectMock = $this->createMock(\Bolt\Boltpay\Helper\Cart::class);
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->contextMock->method('getBugsnagHelper')->willReturn($this->bugsnagHelperMock);
        $this->currentMock = $this->getMockBuilder(CartPlugin::class)
            ->setMethods(['shouldRun'])
            ->setConstructorArgs([$this->contextMock, $this->giftcardCartManagementFactoryMock])
            ->getMock();
        $this->immutableQuoteMock = $this->createPartialMock(
            \Magento\Quote\Model\Quote::class,
            ['getQuoteCurrencyCode', 'getData']
        );
        $this->aheadworksGiftcardManagementMock = $this->getMockBuilder(
            '\Aheadworks\Giftcard\Api\GiftcardCartManagementInterface'
        )
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->setMethods(['get', 'set', 'remove'])
            ->getMock();
    }

    /**
     * @test
     * that __construct sets internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new CartPlugin($this->contextMock, $this->giftcardCartManagementFactoryMock);
        static::assertAttributeEquals(
            $this->giftcardCartManagementFactoryMock,
            'giftcardCartManagementFactory',
            $instance
        );
    }

    /**
     * @test
     * that afterCollectDiscounts will not execute its logic if any of the preconditions are not met
     *
     * @covers ::afterCollectDiscounts
     *
     * @dataProvider afterCollectDiscounts_withVariousPreconditionUnmetStatesProvider
     *
     * @param bool $shouldRun stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * @param bool $isAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $isExists stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists}
     */
    public function afterCollectDiscounts_withVariousPreconditionUnmetStates_doesNotRunPluginLogic(
        $shouldRun,
        $isAvailable,
        $isExists
    ) {
        $this->currentMock->method('shouldRun')->willReturn($shouldRun);
        $this->giftcardCartManagementFactoryMock->method('isAvailable')->willReturn($isAvailable);
        $this->giftcardCartManagementFactoryMock->method('isExists')->willReturn($isExists);
        $result = self::TEST_DEFAULT_RESULT;
        $this->subjectMock->expects(static::never())->method('getLastImmutableQuote');
        $this->giftcardCartManagementFactoryMock->expects(static::never())->method('getInstance');
        static::assertEquals($result, $this->currentMock->afterCollectDiscounts($this->subjectMock, $result));
    }

    /**
     * Data provider for {@see afterCollectDiscounts_withVariousPreconditionUnmetStates_doesNotRunPluginLogic}
     *
     * @return array containing
     * stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable} for giftcard cart management
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists} for giftcard cart management
     */
    public function afterCollectDiscounts_withVariousPreconditionUnmetStatesProvider()
    {
        return [
            ['shouldRun' => false, 'isAvailable' => false, 'isExists' => false],
            ['shouldRun' => false, 'isAvailable' => false, 'isExists' => true],
            ['shouldRun' => false, 'isAvailable' => true, 'isExists' => false],
            ['shouldRun' => false, 'isAvailable' => true, 'isExists' => true],
            ['shouldRun' => true, 'isAvailable' => false, 'isExists' => false],
            ['shouldRun' => true, 'isAvailable' => false, 'isExists' => true],
            ['shouldRun' => true, 'isAvailable' => true, 'isExists' => false],
        ];
    }

    /**
     * @test
     * that afterCollectDiscounts properly adds giftcard data to already collected discounts
     *
     * @dataProvider afterCollectDiscounts_withVariousGiftcardStatesProvider
     *
     * @covers ::afterCollectDiscounts
     *
     * @param array $originalResult of the method call, before the plugin
     * @param array $appliedGiftcards stubbed result of {@see \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface::get}
     * @param array $expectedResult after the plugin execution
     */
    public function afterCollectDiscounts_withVariousGiftcardStates_addsGiftcardDataToCollectedDiscounts(
        $originalResult,
        $appliedGiftcards,
        $expectedResult
    ) {
        $this->currentMock->method('shouldRun')->willReturn(true);
        $this->giftcardCartManagementFactoryMock->method('isAvailable')->willReturn(true);
        $this->giftcardCartManagementFactoryMock->method('isExists')->willReturn(true);

        $this->subjectMock->expects(static::once())->method('getLastImmutableQuote')
            ->willReturn($this->immutableQuoteMock);
        $this->immutableQuoteMock->expects(static::once())->method('getData')->with('bolt_parent_quote_id')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);
        $this->giftcardCartManagementFactoryMock->expects(static::once())->method('getInstance')
            ->willReturn($this->aheadworksGiftcardManagementMock);
        $this->aheadworksGiftcardManagementMock->expects(static::once())->method('get')
            ->with(self::PARENT_QUOTE_ID, false)->willReturn($appliedGiftcards);
        static::assertEquals(
            $expectedResult,
            $this->currentMock->afterCollectDiscounts($this->subjectMock, $originalResult)
        );
    }

    /**
     * Data provider for @see afterCollectDiscounts_withVariousGiftcardStates_addsGiftcardDataToCollectedDiscounts
     */
    public function afterCollectDiscounts_withVariousGiftcardStatesProvider()
    {
        return [
            'No applied giftcards'                      => [
                'originalResult'   => self::TEST_DEFAULT_RESULT,
                'appliedGiftcards' => [],
                'expectedResult'   => self::TEST_DEFAULT_RESULT
            ],
            'Single giftcard'                           => [
                'originalResult'   => self::TEST_DEFAULT_RESULT,
                'appliedGiftcards' => [
                    new \Magento\Framework\DataObject(
                        ['giftcard_code' => 'QWERTY12345', 'giftcard_amount' => 12.34, 'giftcard_balance' => 1000]
                    )
                ],
                'expectedResult'   => [
                    [
                        [
                            'description' => 'Gift Card (QWERTY12345)',
                            'amount'      => 100000,
                            'type'        => 'fixed_amount'
                        ]
                    ],
                    11111,
                    0
                ]
            ],
            'Multiple giftcards'                        => [
                'originalResult'   => self::TEST_DEFAULT_RESULT,
                'appliedGiftcards' => [
                    new \Magento\Framework\DataObject(
                        ['giftcard_code' => 'QWERTY12345', 'giftcard_amount' => 100, 'giftcard_balance' => 100]
                    ),
                    new \Magento\Framework\DataObject(
                        ['giftcard_code' => 'ASDFG54321', 'giftcard_amount' => 23.45, 'giftcard_balance' => 500]
                    ),
                ],
                'expectedResult'   => [
                    [
                        [
                            'description' => 'Gift Card (QWERTY12345)',
                            'amount'      => 10000,
                            'type'        => 'fixed_amount'
                        ],
                        [
                            'description' => 'Gift Card (ASDFG54321)',
                            'amount'      => 50000,
                            'type'        => 'fixed_amount'
                        ],
                    ],
                    0,
                    0
                ]
            ],
            'Multiple giftcards with existing discount' => [
                'originalResult'   => [
                    [
                        [
                            'description' => 'Discount Test',
                            'amount'      => 2000,
                            'type'        => 'fixed_amount'
                        ]
                    ],
                    10345,
                    0
                ],
                'appliedGiftcards' => [
                    new \Magento\Framework\DataObject(
                        ['giftcard_code' => 'QWERTY12345', 'giftcard_amount' => 20, 'giftcard_balance' => 20]
                    ),
                    new \Magento\Framework\DataObject(
                        ['giftcard_code' => 'ASDFG54321', 'giftcard_amount' => 23.45, 'giftcard_balance' => 500]
                    ),
                ],
                'expectedResult'   => [
                    [
                        [
                            'description' => 'Discount Test',
                            'amount'      => 2000,
                            'type'        => 'fixed_amount'
                        ],
                        [
                            'description' => 'Gift Card (QWERTY12345)',
                            'amount'      => 2000,
                            'type'        => 'fixed_amount'
                        ],
                        [
                            'description' => 'Gift Card (ASDFG54321)',
                            'amount'      => 50000,
                            'type'        => 'fixed_amount'
                        ],
                    ],
                    6000,
                    0
                ]
            ],
        ];
    }

    /**
     * @test
     * that afterCollectDiscounts will not interrupt execution if an exception occurs when retireving quote giftcards
     *
     * @covers ::afterCollectDiscounts
     */
    public function afterCollectDiscounts_ifUnableToRetrieveGiftcards_notifiesException()
    {
        $this->currentMock->method('shouldRun')->willReturn(true);
        $this->giftcardCartManagementFactoryMock->method('isAvailable')->willReturn(true);
        $this->giftcardCartManagementFactoryMock->method('isExists')->willReturn(true);

        $this->subjectMock->expects(static::once())->method('getLastImmutableQuote')
            ->willReturn($this->immutableQuoteMock);
        $this->immutableQuoteMock->expects(static::once())->method('getData')->with('bolt_parent_quote_id')
            ->willReturn(self::PARENT_QUOTE_ID);
        $this->immutableQuoteMock->expects(static::once())->method('getQuoteCurrencyCode')
            ->willReturn(self::CURRENCY_CODE);

        $this->giftcardCartManagementFactoryMock->expects(static::once())->method('getInstance')
            ->willReturn($this->aheadworksGiftcardManagementMock);
        $exception = new NoSuchEntityException(__('Cart %1 doesn\'t contain products', self::PARENT_QUOTE_ID));
        $this->aheadworksGiftcardManagementMock->expects(static::once())->method('get')
            ->with(self::PARENT_QUOTE_ID, false)->willThrowException($exception);
        $this->bugsnagHelperMock->expects(static::once())->method('notifyException')->with($exception);
        static::assertEquals(
            self::TEST_DEFAULT_RESULT,
            $this->currentMock->afterCollectDiscounts($this->subjectMock, self::TEST_DEFAULT_RESULT)
        );
    }
}
