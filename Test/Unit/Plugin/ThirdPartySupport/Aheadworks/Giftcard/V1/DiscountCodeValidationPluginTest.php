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
use Aheadworks\Giftcard\Api\GiftcardRepositoryInterface;
use Bolt\Boltpay\Model\Api\DiscountCodeValidation;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\DiscountCodeValidationPlugin;
use Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject as MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1\DiscountCodeValidationPlugin
 */
class DiscountCodeValidationPluginTest extends TestCase
{

    /** @var string Test giftcard code */
    const TEST_GIFTCARD_CODE = 'QWERTY12345';

    /** @var int Test website ID */
    const TEST_WEBSITE_ID = 1;

    /** @var int Test parent quote id */
    const PARENT_QUOTE_ID = 455;

    /** @var string Test default currency code */
    const CURRENCY_CODE = 'USD';

    /**
     * @var CommonModuleContext|MockObject mocked instance of the module context
     */
    private $contextMock;

    /**
     * @var ThirdPartyModuleFactory|MockObject mocked instance of the giftcard cart management factory
     */
    private $aheadworksGiftcardCartManagementFactoryMock;

    /**
     * @var ThirdPartyModuleFactory|MockObject mocked instance of the giftcard repostiory factory
     */
    private $aheadworksGiftcardRepositoryFactoryMock;

    /**
     * @var DiscountCodeValidation|MockObject mocked instance of the plugged class
     */
    private $subjectMock;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag|MockObject mocked instance of the Bolt Bugsnag helper
     */
    private $bugsnagHelperMock;

    /**
     * @var DiscountCodeValidationPlugin|MockObject mocked instance of the tested method
     */
    private $currentMock;

    /**
     * @var GiftcardRepositoryInterface|MockObject mocked instance of the Aheadworks Giftcard repository
     */
    private $aheadworksGiftcardRepositoryMock;

    /**
     * @var GiftcardCartManagementInterface|MockObject mocked instance of the Aheadworks Giftcard cart management
     */
    private $aheadworksGiftcardManagementMock;

    /**
     * @var \Magento\Quote\Model\Quote|MockObject mocked instance of the Magento Quote
     */
    private $parentQuote;

    /**
     * @var \Magento\Quote\Model\Quote|MockObject mocked instance of the Magento Quote
     */
    private $immutableQuote;

    /**
     * @var \Bolt\Boltpay\Helper\Log|MockObject mocked instance of the Bolt logging helper
     */
    private $logHelperMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->contextMock = $this->createMock(CommonModuleContext::class);
        $this->aheadworksGiftcardRepositoryFactoryMock = $this->createMock(ThirdPartyModuleFactory::class);
        $this->aheadworksGiftcardCartManagementFactoryMock = $this->createMock(ThirdPartyModuleFactory::class);
        $this->subjectMock = $this->createMock(DiscountCodeValidation::class);
        $this->bugsnagHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Bugsnag::class);
        $this->logHelperMock = $this->createMock(\Bolt\Boltpay\Helper\Log::class);
        $this->contextMock->method('getBugsnagHelper')->willReturn($this->bugsnagHelperMock);
        $this->contextMock->method('getLogHelper')->willReturn($this->logHelperMock);
        $this->currentMock = $this->getMockBuilder(DiscountCodeValidationPlugin::class)
            ->setMethods(['shouldRun'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->aheadworksGiftcardRepositoryFactoryMock,
                    $this->aheadworksGiftcardCartManagementFactoryMock
                ]
            )
            ->getMock();
        $this->aheadworksGiftcardRepositoryMock = $this->getMockBuilder(
            '\Aheadworks\Giftcard\Api\GiftcardRepositoryInterface'
        )
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->setMethods(
                [
                    'save',
                    'get',
                    'getByCode',
                    'getList',
                    'delete',
                    'deleteById',
                ]
            )
            ->getMock();

        $this->aheadworksGiftcardManagementMock = $this->getMockBuilder(
            '\Aheadworks\Giftcard\Api\GiftcardCartManagementInterface'
        )
            ->disableOriginalClone()
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->setMethods(['get', 'set', 'remove'])
            ->getMock();
        $this->parentQuote = $this->createPartialMock(
            \Magento\Quote\Model\Quote::class,
            ['getId', 'getQuoteCurrencyCode']
        );
        $this->parentQuote->method('getId')->willReturn(self::PARENT_QUOTE_ID);
        $this->parentQuote->method('getQuoteCurrencyCode')->willReturn(self::CURRENCY_CODE);
        $this->immutableQuote = $this->createMock(\Magento\Quote\Model\Quote::class);
    }

    /**
     * @test
     * that constructor assigns provided parameters to properties
     *
     * @covers ::__construct
     */
    public function __construct_always_configuresPropertiesWithProvidedParameters()
    {
        $instance = new DiscountCodeValidationPlugin(
            $this->contextMock,
            $this->aheadworksGiftcardRepositoryFactoryMock,
            $this->aheadworksGiftcardCartManagementFactoryMock
        );
        static::assertAttributeEquals(
            $this->aheadworksGiftcardRepositoryFactoryMock,
            'aheadworksGiftcardRepositoryFactory',
            $instance
        );
        static::assertAttributeEquals(
            $this->aheadworksGiftcardCartManagementFactoryMock,
            'aheadworksGiftcardCartManagementFactory',
            $instance
        );
    }

    /**
     * @test
     * that aroundLoadGiftCardData will not execute its logic and call proceed if any of the preconditions are not met
     *
     * @covers ::aroundLoadGiftCardData
     *
     * @dataProvider aroundLoadGiftCardData_withVariousPreconditionUnmetStatesProvider
     *
     * @param bool $shouldRun stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * @param bool $isAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $isExists stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists}
     */
    public function aroundLoadGiftCardData_withVariousPreconditionUnmetStates_doesNotRunPluginLogic(
        $shouldRun,
        $isAvailable,
        $isExists
    ) {
        $this->currentMock->method('shouldRun')->willReturn($shouldRun);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isAvailable')->willReturn($isAvailable);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isExists')->willReturn($isExists);
        $result = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->getMock();
        $this->aheadworksGiftcardRepositoryFactoryMock->expects(static::never())->method('getInstance');

        $this->subjectMock->expects(static::once())->method('loadGiftCardData')
            ->with(self::TEST_GIFTCARD_CODE, self::TEST_WEBSITE_ID)
            ->willReturn($result);

        static::assertEquals(
            $result,
            $this->currentMock->aroundLoadGiftCardData(
                $this->subjectMock,
                [$this->subjectMock, 'loadGiftCardData'],
                self::TEST_GIFTCARD_CODE,
                self::TEST_WEBSITE_ID
            )
        );
    }

    /**
     * Data provider for {@see aroundLoadGiftCardData_withVariousPreconditionUnmetStates_doesNotRunPluginLogic}
     *
     * @return array containing
     * stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable} for giftcard cart management
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists} for giftcard cart management
     */
    public function aroundLoadGiftCardData_withVariousPreconditionUnmetStatesProvider()
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
     * that aroundLoadGiftCardData will return Aheadworks Giftcard object if one is successfully loaded
     * based on provided giftcard code and website id instead of calling (proceeding) to the original method
     *
     * @covers ::aroundLoadGiftCardData
     */
    public function aroundLoadGiftCardData_ifAheadworksGiftcardFound_returnsAheadworksGiftcard()
    {
        $this->currentMock->method('shouldRun')->willReturn(true);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isAvailable')->willReturn(true);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isExists')->willReturn(true);
        $result = $this->getMockBuilder('\Aheadworks\Giftcard\Api\Data\GiftcardInterface')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->getMock();
        $this->aheadworksGiftcardRepositoryFactoryMock->expects(static::once())->method('getInstance')
            ->willReturn($this->aheadworksGiftcardRepositoryMock);

        $this->subjectMock->expects(static::never())->method('loadGiftCardData');

        $this->aheadworksGiftcardRepositoryMock->expects(static::once())->method('getByCode')
            ->with(self::TEST_GIFTCARD_CODE, self::TEST_WEBSITE_ID)
            ->willReturn($result);

        static::assertEquals(
            $result,
            $this->currentMock->aroundLoadGiftCardData(
                $this->subjectMock,
                [$this->subjectMock, 'loadGiftCardData'],
                self::TEST_GIFTCARD_CODE,
                self::TEST_WEBSITE_ID
            )
        );
    }

    /**
     * @test
     * that aroundLoadGiftCardData will proceed to the original method call if unable to load Aheadworks Giftcard
     * one is successfully loaded based on provided giftcard code and website id
     *
     * @covers ::aroundLoadGiftCardData
     */
    public function aroundLoadGiftCardData_ifAheadworksGiftcardNotFound_proceedsToTheOriginalMethodCall()
    {
        $this->currentMock->method('shouldRun')->willReturn(true);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isAvailable')->willReturn(true);
        $this->aheadworksGiftcardRepositoryFactoryMock->method('isExists')->willReturn(true);
        $result = $this->getMockBuilder('\Magento\GiftCardAccount\Model\Giftcardaccount')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->getMock();

        $this->aheadworksGiftcardRepositoryFactoryMock->expects(static::once())->method('getInstance')
            ->willReturn($this->aheadworksGiftcardRepositoryMock);
        $this->aheadworksGiftcardRepositoryMock->expects(static::once())->method('getByCode')
            ->with(self::TEST_GIFTCARD_CODE, self::TEST_WEBSITE_ID)
            ->willThrowException(NoSuchEntityException::singleField('giftcardCode', self::TEST_GIFTCARD_CODE));

        $this->subjectMock->expects(static::once())->method('loadGiftCardData')
            ->with(self::TEST_GIFTCARD_CODE, self::TEST_WEBSITE_ID)
            ->willReturn($result);

        static::assertEquals(
            $result,
            $this->currentMock->aroundLoadGiftCardData(
                $this->subjectMock,
                [$this->subjectMock, 'loadGiftCardData'],
                self::TEST_GIFTCARD_CODE,
                self::TEST_WEBSITE_ID
            )
        );
    }

    /**
     * @test
     * that aroundApplyingGiftCardCode will not execute its logic and call proceed if any of the preconditions are not met
     *
     * @covers ::aroundApplyingGiftCardCode
     *
     * @dataProvider aroundApplyingGiftCardCode_withVariousPreconditionStatesProvider
     *
     * @param bool $shouldRun stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * @param bool $isAvailable stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable}
     * @param bool $isExists stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists}
     * @param bool $giftcardIsAheadworks whether the provided giftcard belongs to Aheadworks
     *
     * @throws \Exception from the tested method
     */
    public function aroundApplyingGiftCardCode_withVariousPreconditionStates_runsPluginLogicIfPreconditionsAreMet(
        $shouldRun,
        $isAvailable,
        $isExists,
        $giftcardIsAheadworks
    ) {
        $this->currentMock->method('shouldRun')->willReturn($shouldRun);
        $this->aheadworksGiftcardCartManagementFactoryMock->method('isAvailable')->willReturn($isAvailable);
        $this->aheadworksGiftcardCartManagementFactoryMock->method('isExists')->willReturn($isExists);
        $giftcard = $this->getMockBuilder(
            $giftcardIsAheadworks
                ? '\Aheadworks\Giftcard\Api\Data\GiftcardInterface'
                : '\Magento\GiftCardAccount\Model\Giftcardaccount'
        )
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->setMethods(
                [
                    'getId',
                    'setId',
                    'getCode',
                    'setCode',
                    'getType',
                    'setType',
                    'getCreatedAt',
                    'setCreatedAt',
                    'getExpireAt',
                    'setExpireAt',
                    'getWebsiteId',
                    'setWebsiteId',
                    'getBalance',
                    'setBalance',
                    'getInitialBalance',
                    'setInitialBalance',
                    'getState',
                    'setState',
                    'getOrderId',
                    'setOrderId',
                    'getProductId',
                    'setProductId',
                    'getEmailTemplate',
                    'setEmailTemplate',
                    'getSenderName',
                    'setSenderName',
                    'getSenderEmail',
                    'setMessage',
                    'setSenderEmail',
                    'getRecipientName',
                    'setRecipientName',
                    'getRecipientEmail',
                    'setRecipientEmail',
                    'getDeliveryDate',
                    'setDeliveryDate',
                    'getDeliveryDateTimezone',
                    'setDeliveryDateTimezone',
                    'getEmailSent',
                    'setEmailSent',
                    'getHeadline',
                    'setHeadline',
                    'getMessage',
                    'setExtensionAttributes',
                    'getCurrentHistoryAction',
                    'setCurrentHistoryAction',
                    'getExtensionAttributes',
                ]
            )
            ->getMock();
        $giftcard->method('getCode')->willReturn(self::TEST_GIFTCARD_CODE);
        $giftcard->method('getBalance')->willReturn(123.45);

        $preconditionsMet = $shouldRun && $isAvailable && $isExists && $giftcardIsAheadworks;
        $this->aheadworksGiftcardCartManagementFactoryMock->expects(
            $preconditionsMet
                ? static::once()
                : static::never()
        )->method('getInstance')->willReturn($this->aheadworksGiftcardManagementMock);
        if ($preconditionsMet) {
            $this->aheadworksGiftcardManagementMock->expects(static::once())
                ->method('remove')->with(self::PARENT_QUOTE_ID, self::TEST_GIFTCARD_CODE, false)
                ->willThrowException(new CouldNotSaveException(__('The specified Gift Card code not be removed')));
            $this->aheadworksGiftcardManagementMock->expects(static::once())
                ->method('set')->with(self::PARENT_QUOTE_ID, self::TEST_GIFTCARD_CODE, false);
            $result = [
                'status'          => 'success',
                'discount_code'   => 'QWERTY12345',
                'discount_amount' => 12345,
                'description'     => __('Gift Card (%1)', self::TEST_GIFTCARD_CODE),
                'discount_type'   => 'fixed_amount'
            ];
        } else {
            $result = [];
        }

        $this->subjectMock->expects($preconditionsMet ? static::never() : static::once())
            ->method('applyingGiftCardCode')
            ->with(
                self::TEST_GIFTCARD_CODE,
                $giftcard,
                $this->immutableQuote,
                $this->parentQuote
            )
            ->willReturn($result);

        static::assertEquals(
            $result,
            $this->currentMock->aroundApplyingGiftCardCode(
                $this->subjectMock,
                [$this->subjectMock, 'applyingGiftCardCode'],
                self::TEST_GIFTCARD_CODE,
                $giftcard,
                $this->immutableQuote,
                $this->parentQuote
            )
        );
    }

    /**
     * Data provider for {@see aroundApplyingGiftCardCode_withVariousPreconditionStates_doesNotRunPluginLogic}
     *
     * @return array containing
     * stubbed result of {@see \Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin::shouldRun}
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isAvailable} for giftcard cart management
     * stubbed result of {@see \Bolt\Boltpay\Model\ThirdPartyModuleFactory::isExists} for giftcard cart management
     * and whether the provided giftcard belongs to Aheadworks
     */
    public function aroundApplyingGiftCardCode_withVariousPreconditionStatesProvider()
    {
        return \Bolt\Boltpay\Test\Unit\TestHelper::getAllBooleanCombinations(4);
    }

    /**
     * @test
     * that aroundApplyingGiftCardCode will send error response if unable to apply Aheadworks Giftcard
     *
     * @covers ::aroundApplyingGiftCardCode
     *
     * @throws \Exception from tested method
     */
    public function aroundApplyingGiftCardCode_ifUnableToApplyAheadworksGiftcard_sendsErrorResponse()
    {
        $this->currentMock->method('shouldRun')->willReturn(true);
        $this->aheadworksGiftcardCartManagementFactoryMock->method('isAvailable')->willReturn(true);
        $this->aheadworksGiftcardCartManagementFactoryMock->method('isExists')->willReturn(true);
        $giftcard = $this->getMockBuilder('\Aheadworks\Giftcard\Api\Data\GiftcardInterface')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->setMethods(
                [
                    'getId',
                    'setId',
                    'getCode',
                    'setCode',
                    'getType',
                    'setType',
                    'getCreatedAt',
                    'setCreatedAt',
                    'getExpireAt',
                    'setExpireAt',
                    'getWebsiteId',
                    'setWebsiteId',
                    'getBalance',
                    'setBalance',
                    'getInitialBalance',
                    'setInitialBalance',
                    'getState',
                    'setState',
                    'getOrderId',
                    'setOrderId',
                    'getProductId',
                    'setProductId',
                    'getEmailTemplate',
                    'setEmailTemplate',
                    'getSenderName',
                    'setSenderName',
                    'getSenderEmail',
                    'setMessage',
                    'setSenderEmail',
                    'getRecipientName',
                    'setRecipientName',
                    'getRecipientEmail',
                    'setRecipientEmail',
                    'getDeliveryDate',
                    'setDeliveryDate',
                    'getDeliveryDateTimezone',
                    'setDeliveryDateTimezone',
                    'getEmailSent',
                    'setEmailSent',
                    'getHeadline',
                    'setHeadline',
                    'getMessage',
                    'setExtensionAttributes',
                    'getCurrentHistoryAction',
                    'setCurrentHistoryAction',
                    'getExtensionAttributes',
                ]
            )
            ->getMock();
        $giftcard->method('getCode')->willReturn(self::TEST_GIFTCARD_CODE);
        $giftcard->method('getBalance')->willReturn(123.45);

        $this->aheadworksGiftcardCartManagementFactoryMock->expects(static::once())
            ->method('getInstance')->willReturn($this->aheadworksGiftcardManagementMock);
        $this->aheadworksGiftcardManagementMock->expects(static::once())
            ->method('remove')->with(self::PARENT_QUOTE_ID, self::TEST_GIFTCARD_CODE, false)
            ->willThrowException(new CouldNotSaveException(__('The specified Gift Card code not be removed')));
        $exception = new CouldNotSaveException(__('The specified Gift Card code not be added'));
        $this->aheadworksGiftcardManagementMock->expects(static::once())
            ->method('set')->with(self::PARENT_QUOTE_ID, self::TEST_GIFTCARD_CODE, false)
            ->willThrowException($exception);

        $this->subjectMock->expects(static::never())
            ->method('applyingGiftCardCode')
            ->with(
                self::TEST_GIFTCARD_CODE,
                $giftcard,
                $this->immutableQuote,
                $this->parentQuote
            );

        $this->subjectMock->expects(static::once())->method('sendErrorResponse')
            ->with(
                \Bolt\Boltpay\Model\ErrorResponse::ERR_SERVICE,
                $exception->getMessage(),
                422,
                $this->immutableQuote
            );

        static::assertFalse(
            $this->currentMock->aroundApplyingGiftCardCode(
                $this->subjectMock,
                [$this->subjectMock, 'applyingGiftCardCode'],
                self::TEST_GIFTCARD_CODE,
                $giftcard,
                $this->immutableQuote,
                $this->parentQuote
            )
        );
    }
}
