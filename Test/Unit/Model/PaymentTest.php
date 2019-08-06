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

use Bolt\Boltpay\Model\Payment as BoltPayment;
use Bolt\Boltpay\Model\Response;
use Magento\Framework\App\State;
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
use \Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;

/**
 * Class PaymentTest
 */
class PaymentTest extends TestCase
{
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
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var TransactionRepository
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
     * @var ScopeConfigInterface
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
     * @var BoltPayment
     */
    private $currentMock;

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
    public function voidPayment_success()
    {
        $this->mockApiResponse('{"status": "cancelled", "reference": "ABCD-1234-XXXX"}');
        $orderMock = $this->getMockBuilder(\Magento\Sales\Model\Order::class)->disableOriginalConstructor()->getMock();
        $paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getOrder'])->getMockForAbstractClass();
        $paymentMock->method('getAdditionalInformation')->with('real_transaction_id')->willReturn('ABCD-1234-XXXX');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $this->orderHelper->expects($this->once())->method('updateOrderPayment');

        $this->currentMock->void($paymentMock);
    }

    /**
     * @test
     */
    public function voidPayment_throwExceptionWhenBoltRespondWithError()
    {
        $this->expectException(LocalizedException::class);

        $this->mockApiResponse('{"status": "error", "message": "Unknown error"}');
        $orderMock = $this->getMockBuilder(\Magento\Sales\Model\Order::class)->disableOriginalConstructor()->getMock();
        $paymentMock = $this->getMockBuilder(InfoInterface::class)->setMethods(['getOrder'])->getMockForAbstractClass();
        $paymentMock->method('getAdditionalInformation')->with('real_transaction_id')->willReturn('ABCD-1234-XXXX');
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->currentMock->void($paymentMock);
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
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->authSession = $this->createMock(Session::class);
        $this->paymentInfo = $this->createMock(InfoInterface::class);

        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')->willReturn(new DataObject());
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
                                    $this->dataObjectFactory,
                                    $this->cartHelper,
                                    $this->transactionRepository,
                                    $this->authSession
                                  ])->getMock();

        $this->currentMock->method('getInfoInstance')
                          ->willReturn($this->paymentInfo);

        return $this->currentMock;
    }

    private function mockApiResponse($responseJSON) {
        $this->apiHelper->expects($this->once())->method('buildRequest')->willReturn(new Request());
        $response = new Response();
        $response->setResponse(json_decode($responseJSON));
        $this->apiHelper->expects($this->once())->method('sendRequest')->willReturn($response);
    }
}
