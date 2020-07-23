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

namespace Bolt\Boltpay\Test\Unit\Controller\Adminhtml\Customer;

use Bolt\Boltpay\Controller\Adminhtml\Customer\CreditCard;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Address\Mapper;
use Magento\Framework\DataObjectFactory as ObjectFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Customer\Helper\View;
use Magento\Framework\Math\Random;
use \Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Customer\Model\Customer\Mapper as CustomerMapper;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\View\LayoutFactory as LayoutFactory;
use Magento\Framework\View\Result\LayoutFactory as ResultLayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\JsonFactory;

class CreditCardTest extends TestCase
{
    /**
     * @var MockObject|CreditCard
     */
    private $currentMock;

    /**
     * @var MockObject|Context
     */
    private $contextMock;

    /**
     * @var MockObject|Registry
     */
    private $coreRegistryMock;

    /**
     * @var MockObject|FileFactory
     */
    private $fileFactoryMock;

    /**
     * @var MockObject|CustomerFactory
     */
    private $customerFactoryMock;

    /**
     * @var MockObject|AddressFactory
     */
    private $addressFactoryMock;

    /**
     * @var MockObject|FormFactory
     */
    private $formFactoryMock;

    /**
     * @var MockObject|SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var MockObject|View
     */
    private $viewHelperMock;

    /**
     * @var MockObject|Random
     */
    private $randomMock;

    /**
     * @var MockObject|CustomerRepositoryInterface
     */
    private $customerRepositoryMock;

    /**
     * @var MockObject|ExtensibleDataObjectConverter
     */
    private $extensibleDataObjectConverterMock;

    /**
     * @var MockObject|Mapper
     */
    private $addressMapperMock;

    /**
     * @var MockObject|AccountManagementInterface
     */
    private $customerAccountManagementMock;

    /**
     * @var MockObject|AddressRepositoryInterface
     */
    private $addressRepositoryMock;

    /**
     * @var MockObject|CustomerInterfaceFactory
     */
    private $customerDataFactoryMock;

    /**
     * @var MockObject|CustomerInterfaceFactory
     */
    private $addressDataFactoryMock;

    /**
     * @var MockObject|CustomerMapper
     */
    private $customerMapperMock;

    /**
     * @var MockObject|DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var MockObject|DataObjectHelper
     */
    private $dataObjectHelperMock;

    /**
     * @var MockObject|ObjectFactory
     */
    private $objectFactoryMock;

    /**
     * @var MockObject|LayoutFactory
     */
    private $layoutFactoryMock;

    /**
     * @var MockObject|ResultLayoutFactory
     */
    private $resultLayoutFactoryMock;

    /**
     * @var MockObject|PageFactory
     */
    private $resultPageFactoryMock;

    /**
     * @var MockObject|ForwardFactory
     */
    private $resultForwardFactoryMock;

    /**
     * @var MockObject|JsonFactory
     */
    private $resultJsonFactoryMock;

    protected function setUp()
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->coreRegistryMock = $this->createMock(Registry::class);
        $this->fileFactoryMock = $this->createMock(FileFactory::class);
        $this->customerFactoryMock = $this->createMock(CustomerFactory::class);
        $this->addressFactoryMock = $this->createMock(AddressFactory::class);
        $this->formFactoryMock = $this->createMock(FormFactory::class);
        $this->subscriberFactory = $this->createMock(SubscriberFactory::class);
        $this->viewHelperMock = $this->createMock(View::class);
        $this->randomMock = $this->createMock(Random::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepositoryInterface::class);
        $this->extensibleDataObjectConverterMock = $this->createMock(ExtensibleDataObjectConverter::class);
        $this->addressMapperMock = $this->createMock(Mapper::class);
        $this->customerAccountManagementMock = $this->createMock(AccountManagementInterface::class);
        $this->addressRepositoryMock = $this->createMock(AddressRepositoryInterface::class);
        $this->customerDataFactoryMock = $this->createMock(CustomerInterfaceFactory::class);
        $this->addressDataFactoryMock = $this->createMock(AddressInterfaceFactory::class);
        $this->customerMapperMock = $this->createMock(CustomerMapper::class);
        $this->dataObjectProcessor = $this->createMock(DataObjectProcessor::class);
        $this->dataObjectHelperMock = $this->createMock(DataObjectHelper::class);
        $this->objectFactoryMock = $this->createMock(ObjectFactory::class);
        $this->layoutFactoryMock = $this->createMock(LayoutFactory::class);
        $this->resultLayoutFactoryMock = $this->createMock(ResultLayoutFactory::class);
        $this->resultPageFactoryMock = $this->createMock(PageFactory::class);
        $this->resultForwardFactoryMock = $this->createMock(ForwardFactory::class);
        $this->resultJsonFactoryMock = $this->createMock(JsonFactory::class);


        $this->currentMock = $this->getMockBuilder(CreditCard::class)
            ->setConstructorArgs([
                $this->contextMock,
                $this->coreRegistryMock,
                $this->fileFactoryMock,
                $this->customerFactoryMock,
                $this->addressFactoryMock,
                $this->formFactoryMock,
                $this->subscriberFactory,
                $this->viewHelperMock,
                $this->randomMock,
                $this->customerRepositoryMock,
                $this->extensibleDataObjectConverterMock,
                $this->addressMapperMock,
                $this->customerAccountManagementMock,
                $this->addressRepositoryMock,
                $this->customerDataFactoryMock,
                $this->addressDataFactoryMock,
                $this->customerMapperMock,
                $this->dataObjectProcessor,
                $this->dataObjectHelperMock,
                $this->objectFactoryMock,
                $this->layoutFactoryMock,
                $this->resultLayoutFactoryMock,
                $this->resultPageFactoryMock,
                $this->resultForwardFactoryMock,
                $this->resultJsonFactoryMock,
            ])
            ->setMethods(['initCurrentCustomer'])
            ->getMock();
    }


    /**
     * @test
     */
    public function execute()
    {
        $this->currentMock->expects(self::once())->method('initCurrentCustomer')->willReturnSelf();
        $this->resultLayoutFactoryMock->expects(self::once())->method('create')->willReturnSelf();

        $this->currentMock->execute();
    }
}
