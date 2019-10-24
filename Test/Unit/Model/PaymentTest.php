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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Payment as BoltPayment;
use Bolt\Boltpay\Model\Response;
use Magento\Framework\App\Area as AppArea;
use Magento\Framework\App\State;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Api as ApiHelper;
use Bolt\Boltpay\Model\Request;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use \Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class PaymentTest
 * @coversDefaultClass \Bolt\Boltpay\Model\Payment
 */
class PaymentTest extends TestCase
{
    const TITLE = 'Bolt Pay';
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var MockObject|Order
     */
    private $orderMock;

    /**
     * @var MockObject|Bugsnag
     */
    private $bugsnag;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var MockObject|CartHelper
     */
    private $cartHelper;

    /**
     * @var MockObject|TransactionRepository
     */
    protected $transactionRepository;

    /**
     * @var Session
     */
    protected $authSession;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ExtensionAttributesFactory
     */
    private $extensionFactory;

    /**
     * @var AttributeValueFactory
     */
    private $customAttributeFactory;

    /**
     * @var Data
     */
    private $paymentData;

    /**
     * @var MockObject|ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var InfoInterface
     */
    private $paymentInfo;

    /**
     * @var InfoInterface
     */
    private $paymentMock;

    /**
     * @var MockObject|BoltPayment
     */
    private $currentMock;

    /**
     * @var MockObject|MetricsClient
     */
    private $metricsClient;

    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    /**
     * @test
     */
    public function testCanReviewPayment()
    {
        $this->paymentInfo->method('getAdditionalInformation')
            ->with('transaction_state')
            ->willReturn(true);

        $this->assertTrue($this->currentMock->canReviewPayment());
    }

    /**
     * @test
     */
    public function testCannotReviewPayment()
    {
        $this->paymentInfo->method('getAdditionalInformation')
            ->with('transaction_state')
            ->willReturn(false);

        $this->assertFalse($this->currentMock->canReviewPayment());
    }

    /**
     * @test
     */
    public function cancel()
    {
        $this->mockApiResponse(
            "merchant/transactions/void",
            '{"status": "cancelled", "reference": "ABCD-1234-XXXX"}'
        );
        $this->currentMock->cancel($this->paymentMock);
    }

    /**
     * @test
     */
    public function voidPayment_success()
    {
        $this->mockApiResponse(
            "merchant/transactions/void",
            '{"status": "cancelled", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderHelper->expects($this->once())->method('updateOrderPayment');

        $this->currentMock->void($this->paymentMock);
    }

    /**
     * @test
     */
    public function voidPayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->expectException(LocalizedException::class);

        $this->mockApiResponse(
            "merchant/transactions/void",
            '{"status": "error", "message": "Unknown error"}'
        );
        $this->orderHelper->expects($this->never())->method('updateOrderPayment');

        $this->currentMock->void($this->paymentMock);
    }

    /**
     * @test
     */
    public function voidPayment_noTransaction()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please wait while transaction gets updated from Bolt.');

        $paymentInfoMock =  $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $paymentInfoMock->method('getAdditionalInformation')->with('real_transaction_id')
            ->willReturn(null);

        $this->currentMock->void($paymentInfoMock);
    }


    /**
     * @test
     */
    public function voidPayment_noReponse()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Bad void response from boltpay');

        $this->mockApiResponse("merchant/transactions/void", '');

        $this->currentMock->void($this->paymentMock);
    }

    /**
     * @test
     */
    public function capturePayment_success()
    {
        $this->mockApiResponse(
            "merchant/transactions/capture",
            '{"status": "completed", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderHelper->expects($this->once())->method('updateOrderPayment');

        $this->currentMock->capture($this->paymentMock, 100);
    }

    /**
     * @test
     */
    public function capturePayment_success_multicapture()
    {
        // status stays 'authorized' if merchant has auto-capture enabled and some amount remains uncaptured
        $this->mockApiResponse(
            "merchant/transactions/capture",
            '{"status": "authorized", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderHelper->expects($this->once())->method('updateOrderPayment');

        $this->currentMock->capture($this->paymentMock, 100);
    }

    /**
     * @test
     */
    public function capturePayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->expectException(LocalizedException::class);

        $this->mockApiResponse(
            "merchant/transactions/capture",
            '{"status": "error", "message": "Unknown error"}'
        );
        $this->orderHelper->expects($this->never())->method('updateOrderPayment');

        $this->currentMock->capture($this->paymentMock, 100);
    }

    /**
     * @test
     */
    public function capturePayment_invalidAmount()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid amount for capture.');
        $this->currentMock->capture($this->paymentMock, 0);
    }

    /**
     * @test
     */
    public function capturePayment_noTransactionId()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please wait while transaction get updated from Bolt.');

        $paymentInfoMock =  $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $paymentInfoMock->method('getAdditionalInformation')->with('real_transaction_id')
            ->willReturn(null);
        $this->currentMock->capture($paymentInfoMock, 100);
    }

    /**
     * @test
     */
    public function capturePayment_noResponse()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Bad capture response from boltpay');

        $this->mockApiResponse("merchant/transactions/capture", '');

        $this->currentMock->capture($this->paymentMock, 100);
    }

    /**
     * @test
     */
    public function refundPayment_success()
    {
        $this->mockApiResponse(
            "merchant/transactions/credit",
            '{"status": "completed", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderHelper->expects($this->once())->method('updateOrderPayment');

        $this->currentMock->refund($this->paymentMock, 100);
    }

    /**
     * @test
     */
    public function refundPayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->expectException(LocalizedException::class);

        $this->mockApiResponse(
            "merchant/transactions/credit",
            '{"status": "error", "message": "Unknown error"}'
        );
        $this->orderHelper->expects($this->never())->method('updateOrderPayment');

        $this->currentMock->refund($this->paymentMock, 100);
    }


    /**
     * @test
     */
    public function refundPayment_invalidAmount()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid amount for refund.');
        $this->currentMock->refund($this->paymentMock, 0);
    }

    /**
     * @test
     */
    public function refundPayment_noTransactionId()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please wait while transaction get updated from Bolt.');

        $paymentInfoMock =  $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $paymentInfoMock->method('getAdditionalInformation')->with('real_transaction_id')
            ->willReturn(null);
        $this->currentMock->refund($paymentInfoMock, 100);
    }

    /**
     * @test
     */
    public function refundPayment_noResponse()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Bad refund response from boltpay');

        $this->mockApiResponse("merchant/transactions/credit", '');

        $this->currentMock->refund($this->paymentMock, 100);
    }


    /**
     * @test
     */
    public function validate()
    {
        $this->assertEquals(
            $this->currentMock->validate(),
            $this->currentMock
        );
    }

    /**
     * @test
     */
    public function isAvailable_hasProductRestrictions()
    {
        $quoteMock = $this->createMock(Quote::class);
        $this->cartHelper->expects($this->once())->method('hasProductRestrictions')
            ->with($quoteMock)
            ->willReturn(true);
        $this->assertFalse($this->currentMock->isAvailable($quoteMock));
    }

    /**
     * @test
     */
    public function isAvailable_noProductRestrictions()
    {
        $quoteMock = $this->createMock(Quote::class);
        $this->cartHelper->expects($this->once())->method('hasProductRestrictions')
            ->with($quoteMock)
            ->willReturn(false);
        $this->currentMock->isAvailable($quoteMock);
    }

    /**
     * @test
     */
    public function getTitle_parent()
    {
        $this->scopeConfig->expects($this->once())->method('getValue')
            ->with(
                'payment/' . $this->currentMock->getCode() . '/title',
                ScopeInterface::SCOPE_STORE,
                $this->anything()
            )
            ->willReturn(self::TITLE);
        $this->assertEquals($this->currentMock->getTitle(), self::TITLE);
    }

    /**
     * @test
     */
    public function getTitle_adminhtml_noStore()
    {
        $this->setObjectProtectedProperty($this->currentMock, 'areaCode', AppArea::AREA_ADMINHTML);
        $this->scopeConfig->expects($this->once())->method('getValue')
            ->with('payment/' . $this->currentMock->getCode() . '/title', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(self::TITLE);
        $this->assertEquals($this->currentMock->getTitle(), self::TITLE);
    }

    /**
     * @test
     */
    public function getTitle_adminhtml_storeFromData()
    {
        $this->setObjectProtectedProperty($this->currentMock, 'areaCode', AppArea::AREA_ADMINHTML);
        $this->setObjectProtectedProperty($this->currentMock, '_data', ['store' => 1]);
        $this->scopeConfig->expects($this->once())->method('getValue')
            ->with('payment/' . $this->currentMock->getCode() . '/title', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(self::TITLE);
        $this->assertEquals($this->currentMock->getTitle(), self::TITLE);
    }

    /**
     * @test
     */
    public function getTitle_adminhtml_storeFromRegistry()
    {
        $this->setObjectProtectedProperty($this->currentMock, 'areaCode', AppArea::AREA_ADMINHTML);
        $this->setObjectProtectedProperty($this->currentMock, 'registryCurrentOrder', $this->orderMock);
        $this->orderMock->expects($this->exactly(2))->method('getStoreId')->willReturn(1);
        $this->scopeConfig->expects($this->once())->method('getValue')
            ->with('payment/' . $this->currentMock->getCode() . '/title', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(self::TITLE);
        $this->assertEquals($this->currentMock->getTitle(), self::TITLE);
    }

    /**
     * @test
     */
    public function acceptPayment_success()
    {
        $this->mockApiResponse(
            "merchant/transactions/review",
            '{"status": "completed", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderMock->expects($this->once())->method('addStatusHistoryComment');

        $this->assertTrue($this->currentMock->acceptPayment($this->paymentMock));
    }

    /**
     * @test
     */
    public function acceptPayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->mockApiResponse(
            "merchant/transactions/review",
            '{"status": "error", "message": "Unknown error"}'
        );
        $this->assertFalse($this->currentMock->acceptPayment($this->paymentMock));
    }

    /**
     * @test
     */
    public function reviewPayment_noTransaction()
    {
        $paymentInfoMock =  $this->getMockBuilder(InfoInterface::class)->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $paymentInfoMock->method('getAdditionalInformation')->with('real_transaction_id')
            ->willReturn(null);
        $this->bugsnag->expects($this->once())->method('notifyException')
            ->with(new LocalizedException(__('Please wait while transaction gets updated from Bolt.')));

        $this->assertFalse($this->currentMock->acceptPayment($paymentInfoMock));
    }

    /**
     * @test
     */
    public function reviewPayment_noReference()
    {
        $this->mockApiResponse("merchant/transactions/review", '{"reference": null}');
        $this->bugsnag->expects($this->once())->method('notifyException')
            ->with(new LocalizedException(__('Bad review response. Empty transaction reference')));
        $this->assertFalse($this->currentMock->denyPayment($this->paymentMock));
    }

    /**
     * @test
     */
    public function rejectPayment_success()
    {
        $this->mockApiResponse(
            "merchant/transactions/review",
            '{"status": "completed", "reference": "ABCD-1234-XXXX"}'
        );
        $this->orderMock->expects($this->once())->method('addStatusHistoryComment');

        $this->assertTrue($this->currentMock->denyPayment($this->paymentMock));
    }

    /**
     * @test
     */
    public function rejectPayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->mockApiResponse(
            "merchant/transactions/review",
            '{"status": "error", "message": "Unknown error"}'
        );

        $this->assertFalse($this->currentMock->denyPayment($this->paymentMock));
    }

    /**
     * @test
     */
    public function fetchTransactionInfo_success()
    {
        $magentoTxnMock = $this->createMock(Transaction::class);
        $magentoTxnMock->method('getAdditionalInformation')->willReturn(array('Reference' => 'ABCD-XXXX-1234'));
        $this->transactionRepository->method('getByTransactionId')->willReturn($magentoTxnMock);

        $this->orderHelper->expects($this->once())->method('updateOrderPayment');
        $this->metricsClient->expects($this->once())->method('processMetric')
            ->with("order_fetch.success", 1, "order_fetch.latency", $this->anything());

        $this->currentMock->fetchTransactionInfo($this->paymentMock, 'transaction-1');
    }

    /**
     * @test
     */
    public function fetchTransactionInfo_exception()
    {
        $exception = new \Magento\Framework\Exception\InputException(__('Identifying Fields required'));

        $this->transactionRepository->method('getByTransactionId')
            ->willThrowException($exception);

        $this->metricsClient->expects($this->once())->method('processMetric')
            ->with("order_fetch.failure", 1, "order_fetch.latency", $this->anything());
        $this->bugsnag->expects($this->once())->method('notifyException')
            ->with($exception);

        $this->assertEquals(
            $this->currentMock->fetchTransactionInfo($this->paymentMock, 'transaction-1'),
            []
        );
    }

    private function initRequiredMocks()
    {
        $mockAppState = $this->createMock(State::class);
        $this->context = $this->createMock(Context::class);
        $this->context->method('getAppState')->willReturn($mockAppState);

        $this->registry = $this->createMock(Registry::class);
        $this->extensionFactory = $this->createMock(ExtensionAttributesFactory::class);
        $this->customAttributeFactory = $this->createMock(AttributeValueFactory::class);
        $this->paymentData = $this->createMock(Data::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(Logger::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->apiHelper = $this->createMock(ApiHelper::class);
        $this->localeDate = $this->createMock(TimezoneInterface::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->transactionRepository = $this->getMockBuilder(TransactionRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['getByTransactionId'])
            ->getMock();
        $this->authSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUser'])
            ->getMock();
        $this->paymentInfo = $this->createMock(InfoInterface::class);

        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn(new DataObject());
        $this->authSession->method('getUser')->willReturn(new DataObject());

        $this->orderMock = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $this->orderMock->method('getId')->willReturn('order-123');
        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)
            ->setMethods(['getId', 'getOrder'])
            ->getMockForAbstractClass();
        $this->paymentMock->method('getId')->willReturn('payment-1');
        $this->paymentMock->method('getAdditionalInformation')
            ->with('real_transaction_id')
            ->willReturn('ABCD-1234-XXXX');
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
    }

    /**
     * @return BoltPayment
     */
    protected function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(BoltPayment::class)
                                  ->setMethods(['getInfoInstance'])
                                  ->setConstructorArgs([
                                    $this->context,
                                    $this->registry,
                                    $this->extensionFactory,
                                    $this->customAttributeFactory,
                                    $this->paymentData,
                                    $this->scopeConfig,
                                    $this->logger,
                                    $this->localeDate,
                                    $this->configHelper,
                                    $this->apiHelper,
                                    $this->orderHelper,
                                    $this->bugsnag,
                                      $this->metricsClient,
                                    $this->dataObjectFactory,
                                    $this->cartHelper,
                                    $this->transactionRepository,
                                    $this->authSession
                                  ])->getMock();

        $this->currentMock->method('getInfoInstance')
                          ->willReturn($this->paymentInfo);

        return $this->currentMock;
    }

    /**
     * @param string $path
     * @param string $responseJSON
     */
    private function mockApiResponse($path, $responseJSON) {
        $this->apiHelper->expects($this->once())->method('buildRequest')
            ->will($this->returnCallback(
                function($data) use ($path) {
                    $this->assertEquals($path, $data->getDynamicApiUrl());
                    return new Request();
                }));
        $response = new Response();
        $response->setResponse(json_decode($responseJSON));
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn($response);
    }

    /**
     * @param $object
     * @param $property
     * @param $value
     * @throws \ReflectionException
     */
    protected function setObjectProtectedProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass($object);
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($object, $value);
    }
}
