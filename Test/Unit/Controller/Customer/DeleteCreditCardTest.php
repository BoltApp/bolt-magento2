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

namespace Bolt\Boltpay\Test\Unit\Controller\Customer;

use Bolt\Boltpay\Controller\Customer\DeleteCreditCard;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Message\Manager;
use Magento\Store\App\Response\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Model\Session;

class DeleteCreditCardTest extends TestCase
{
    const ID = '1';
    const CUSTOMER_ID = '111';

    /**
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactoryMock;

    /**
     * @var Context
     */
    private $contextMock;

    /**
     * @var Bugsnag
     */
    private $bugsnagMock;

    /**
     * @var DeleteCreditCard
     */
    private $currentMock;

    /**
     * @var Http
     */
    private $requestMock;

    /**
     * @var Manager
     */
    private $messageMock;

    /**
     * @var Redirect
     */
    private $redirectMock;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Validator
     */
    private $validatorMock;

    protected function setUp()
    {
        $this->contextMock = $this->createMock(Context::class);

        $this->bugsnagMock = $this->getMockBuilder(Bugsnag::class)
            ->disableOriginalConstructor()
            ->setMethods(['notifyException'])
            ->getMock();

        $this->customerCreditCardFactoryMock = $this->getMockBuilder(CustomerCreditCardFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create', 'load', 'delete', 'getId', 'getCustomerId'])
            ->getMock();

        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->setMethods(['getParam'])
            ->getMock();

        $this->messageMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->setMethods(['addSuccessMessage', 'addExceptionMessage', 'addErrorMessage'])
            ->getMock();

        $this->validatorMock = $this->getMockBuilder(Validator::class)
            ->disableOriginalConstructor()
            ->setMethods(['validate'])
            ->getMock();

        $this->redirectMock = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRefererUrl'])
            ->getMock();

        $this->customerSession = $this->createPartialMock(Session::class, ['getCustomerId']);

        $this->contextMock->method('getMessageManager')->willReturn($this->messageMock);
        $this->contextMock->method('getRedirect')->willReturn($this->redirectMock);



        $this->currentMock = $this->getMockBuilder(DeleteCreditCard::class)
            ->setConstructorArgs([
                $this->contextMock,
                $this->bugsnagMock,
                $this->customerCreditCardFactoryMock,
                $this->validatorMock,
                $this->customerSession
            ])->setMethods(['getRequest', '_redirect'])
            ->getMock();
    }


    /**
     * @test
     */
    public function execute_success()
    {
        $this->validatorMock->expects(self::once())->method('validate')->with($this->requestMock)->willReturn(true);

        $this->requestMock->expects(self::once())->method('getParam')->willReturn(self::ID);
        $this->messageMock->expects(self::once())
            ->method('addSuccessMessage')
            ->with(__('You deleted the Bolt credit card'))
            ->willReturnSelf();
        $this->redirectMock->expects(self::once())->method('getRefererUrl')->willReturnSelf();

        $this->currentMock->expects(self::any())->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->expects(self::once())->method('_redirect')->with($this->redirectMock)->willReturn($this->redirectMock);

        $this->customerCreditCardFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('load')->with(self::ID)->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('getId')->willReturn(self::ID);

        $this->customerSession->expects(self::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->customerCreditCardFactoryMock->expects(self::once())->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $this->customerCreditCardFactoryMock->expects(self::once())->method('delete')->willReturnSelf();

        $this->assertSame($this->redirectMock, $this->currentMock->execute());
    }

    public function dataProvider_execute_withInvalidIdParameter()
    {
        return [
            [self::ID, 'test', self::CUSTOMER_ID],
            [null, self::CUSTOMER_ID, self::CUSTOMER_ID]
        ];
    }

    /**
     * @param $id
     * @param $customerId
     * @param $customerSessionId
     * @dataProvider dataProvider_execute_withInvalidIdParameter
     * @test
     */
    public function execute_withInvalidIdParameter($id, $customerId, $customerSessionId)
    {
        $this->validatorMock->expects(self::once())->method('validate')->with($this->requestMock)->willReturn(true);

        $this->requestMock->expects(self::once())->method('getParam')->willReturn(self::ID);
        $this->messageMock->expects(self::once())
            ->method('addErrorMessage')
            ->with(__('Credit Card doesn\'t exist'))
            ->willReturnSelf();
        $this->redirectMock->expects(self::once())->method('getRefererUrl')->willReturnSelf();
        $this->currentMock->expects(self::any())->method('getRequest')->willReturn($this->requestMock);
        if ($id) {
            $this->customerSession->expects(self::once())->method('getCustomerId')->willReturn($customerSessionId);
            $this->customerCreditCardFactoryMock->expects(self::once())->method('getCustomerId')->willReturn($customerId);
        }

        $this->currentMock->expects(self::once())->method('_redirect')->with($this->redirectMock)->willReturn($this->redirectMock);

        $this->customerCreditCardFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('load')->with(self::ID)->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('getId')->willReturn($id);
        $this->assertSame($this->redirectMock, $this->currentMock->execute());
    }

    /**
     * @test
     */
    public function execute_withMissingIdParameter()
    {
        $this->validatorMock->expects(self::once())->method('validate')->with($this->requestMock)->willReturn(true);
        $this->requestMock->expects(self::once())->method('getParam')->willReturn(null);
        $this->messageMock->expects(self::once())
            ->method('addErrorMessage')
            ->with(__('Missing id parameter'))
            ->willReturnSelf();
        $this->redirectMock->expects(self::once())->method('getRefererUrl')->willReturnSelf();


        $this->currentMock->expects(self::any())->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->expects(self::once())->method('_redirect')->with($this->redirectMock)->willReturn($this->redirectMock);
        $this->assertSame($this->redirectMock, $this->currentMock->execute());
    }

    /**
     * @test
     */
    public function execute_withException()
    {
        $this->validatorMock->expects(self::once())->method('validate')->with($this->requestMock)->willReturn(true);
        $this->requestMock->expects(self::once())->method('getParam')->willReturn(self::ID);
        $this->messageMock->expects(self::once())
            ->method('addExceptionMessage')
            ->with(new \Exception(__("Delete Error")))
            ->willReturnSelf();

        $this->bugsnagMock->expects(self::once())->method('notifyException')->willReturnSelf();
        $this->redirectMock->expects(self::once())->method('getRefererUrl')->willReturnSelf();

        $this->currentMock->expects(self::any())->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->expects(self::once())->method('_redirect')->with($this->redirectMock)->willReturn($this->redirectMock);

        $this->customerCreditCardFactoryMock->expects(self::once())->method('create')->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('load')->with(self::ID)->willReturnSelf();
        $this->customerCreditCardFactoryMock->expects(self::once())->method('getId')->willReturn(self::ID);
        $this->customerCreditCardFactoryMock->expects(self::once())->method('delete')->willThrowException(new \Exception(__("Delete Error")));

        $this->assertSame($this->redirectMock, $this->currentMock->execute());
    }

    /**
     * @test
     */
    public function execute_withInvalidFormKey()
    {
        $this->validatorMock->expects(self::once())->method('validate')->with($this->requestMock)->willReturn(false);
        $this->messageMock->expects(self::once())
            ->method('addErrorMessage')
            ->with(__('Invalid form key'))
            ->willReturnSelf();
        $this->redirectMock->expects(self::once())->method('getRefererUrl')->willReturnSelf();


        $this->currentMock->expects(self::any())->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->expects(self::once())->method('_redirect')->with($this->redirectMock)->willReturn($this->redirectMock);
        $this->assertSame($this->redirectMock, $this->currentMock->execute());
    }
}
