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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Model\Api\Data\AccountInfo;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Exception;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Bolt\Boltpay\Model\Api\Data\AccountInfoFactory;
use Bolt\Boltpay\Model\Api\CheckAccount;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\CheckAccount
 */
class CheckAccountTest extends TestCase
{
    const EMAIL = 'integration@bolt.com';

    /**
     * @var CheckAccount
     */
    private $checkAccount;

    /**
     * @var AccountInfoFactory
     */
    private $accountInfoFactoryMock;

    /**
     * @var Response
     */
    private $responseMock;

    /**
     * @var HookHelper
     */
    private $hookHelperMock;

    /**
     * @var LogHelper
     */
    private $logHelperMock;

    /**
     * @var MetricsClient
     */
    private $metricsClientMock;

    /*
     * @var StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    private $accountManagementMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->accountInfoFactoryMock = $this->createMock(AccountInfoFactory::class);
        $this->accountInfoFactoryMock->method('create')->willReturn(new AccountInfo());

        // prepare store manager
        $storeInterfaceMock = $this->createMock(StoreInterface::class);
        $storeInterfaceMock->method('getId')->willReturn(0);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($storeInterfaceMock);

        $this->responseMock = $this->createMock(Response::class);

        $this->hookHelperMock = $this->createMock(HookHelper::class);
        $this->hookHelperMock->method('preProcessWebhook');

        $this->logHelperMock = $this->createMock(LogHelper::class);
        $this->metricsClientMock = $this->createMock(MetricsClient::class);
        //$this->errorResponse = $this->createMock(BoltErrorResponse::class);
        $this->accountManagementMock = $this->createMock(AccountManagementInterface::class);

        $objectManager = new ObjectManager($this);
        $this->errorResponse = $objectManager->getObject(BoltErrorResponse::class);
        $this->checkAccount = $objectManager->getObject(
            CheckAccount::class,
            [
                'accountInfoFactory' => $this->accountInfoFactoryMock,
                'response' => $this->responseMock,
                'hookHelper' => $this->hookHelperMock,
                'logHelper' => $this->logHelperMock,
                'metricsClient' => $this->metricsClientMock,
                'storeManager' => $this->storeManagerMock,
                'errorResponse' => $this->errorResponse,
                'accountManagement' => $this->accountManagementMock
            ]
        );
    }

    /**
     * @test
     * @dataProvider checkEmailDataProvider
     */
    public function checkEmail_returnSuccessfulResult($accountExist)
    {
        $this->hookHelperMock->expects($this->once())->method('preProcessWebhook');
        $this->accountManagementMock->expects($this->once())
            ->method('isEmailAvailable')->with(self::EMAIL)->willReturn(!$accountExist);
        $accountInfo = $this->checkAccount->checkEmail(self::EMAIL);

        $this->assertEquals(self::EMAIL, $accountInfo->getEmail());
        $this->assertEquals($accountExist, $accountInfo->getAccountExist());
    }

    public function checkEmailDataProvider()
    {
        return [[true], [false]];
    }

    /**
     * @test
     */
    public function checkEmail_whenPreProcesHookFail_returnErrorAnswer()
    {
        $this->hookHelperMock->expects($this->once())->method('preProcessWebhook')
        ->willThrowException(new WebapiException(__('Precondition Failed'), 6001, 412));
        $this->accountManagementMock->expects($this->never())->method('isEmailAvailable');

        $this->responseMock->expects($this->once())
            ->method('setHttpResponseCode')->with(500);
        $this->responseMock->expects($this->once())
            ->method('setBody')->with('{"status":"failure","error":{"code":6001,"message":"Precondition Failed"}}');
        $this->responseMock->expects($this->once())
            ->method('sendResponse');

        $accountInfo = $this->checkAccount->checkEmail(self::EMAIL);
    }
}