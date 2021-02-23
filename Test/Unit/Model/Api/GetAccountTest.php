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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\GetAccount;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\GetAccount
 */
class GetAccountTest extends BoltTestCase
{
    /**
     * @var Response|MockObject
     */
    private $response;

    /**
     * @var CustomerRepository|MockObject
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var HookHelper|MockObject
     */
    private $hookHelper;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnag;

    /**
     * @var GetAccount|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->response = $this->createMock(Response::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->currentMock = $this->getMockBuilder(GetAccount::class)
            ->setMethods()
            ->setConstructorArgs([
                $this->response,
                $this->customerRepository,
                $this->storeManager,
                $this->hookHelper,
                $this->bugsnag
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function execute_throwsException_ifVerifySignatureFails()
    {
        $this->hookHelper->expects(static::once())->method('verifyRequest')->willReturn(false);
        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Request is not authenticated.');
        $this->currentMock->execute('test@bolt.com');
    }

    /**
     * @test
     */
    public function execute_throwsException_ifEmailIsEmpty()
    {
        $this->hookHelper->expects(static::once())->method('verifyRequest')->willReturn(true);
        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Missing email in the request body.');
        $this->currentMock->execute('');
    }

    /**
     * @test
     */
    public function execute_throwsException_ifEmailNotFound()
    {
        $this->hookHelper->expects(static::once())->method('verifyRequest')->willReturn(true);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $this->customerRepository->expects(static::once())->method('get')->with('test@bolt.com', 1)->willThrowException(new NoSuchEntityException());
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Customer not found with given email.');
        $this->currentMock->execute('test@bolt.com');
    }

    /**
     * @test
     */
    public function execute_throwsException_ifExceptionIsThrown()
    {
        $this->hookHelper->expects(static::once())->method('verifyRequest')->willReturn(true);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $this->customerRepository->expects(static::once())->method('get')->with('test@bolt.com', 1)->willThrowException(new Exception());
        $this->expectException(WebApiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->currentMock->execute('test@bolt.com');
    }

    /**
     * @test
     */
    public function execute_returnsCustomerId_ifEverythingSucceeds()
    {
        $this->hookHelper->expects(static::once())->method('verifyRequest')->willReturn(true);
        $store = $this->createMock(StoreInterface::class);
        $this->storeManager->expects(static::once())->method('getStore')->willReturn($store);
        $store->expects(static::once())->method('getWebsiteId')->willReturn(1);
        $customerInterface = $this->createMock(CustomerInterface::class);
        $this->customerRepository->expects(static::once())->method('get')->with('test@bolt.com', 1)->willReturn($customerInterface);
        $customerInterface->expects(static::once())->method('getId')->willReturn(1);
        $this->response->expects(static::once())->method('setHeader')->with('Content-Type', 'application/json');
        $this->response->expects(static::once())->method('setHttpResponseCode')->with(200);
        $this->response->expects(static::once())->method('setBody')->with(json_encode(['id' => 1]));
        $this->response->expects(static::once())->method('sendResponse');
        $this->currentMock->execute('test@bolt.com');
    }
}
