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

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Form as BlockForm;
use Bolt\Boltpay\Helper\Config;
use Magento\Framework\Session\SessionManager as SessionManager;
use Magento\Backend\Model\Session\Quote as BackendQuote;
use Magento\Framework\View\Element\Template\Context;
use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\CollectionFactory as CustomerCreditCardCollectionFactory;
use Magento\Quote\Model\Quote\Address;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

/**
 * Class FormTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class FormTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Context
     */
    private $contextMock;

    /**
     * @var BlockForm
     */
    private $block;

    /**
     * @var Config
     */
    private $configHelperMock;

    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var BackendQuote
     */
    private $quoteMock;

    /**
     * @var CustomerCreditCardCollectionFactory
     */
    private $customerCreditCardCollectionFactoryMock;

    /**
     * @var Address
     */
    private $addressMock;

    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCard
     */
    private $mockCustomerCreditCard;

    /**
     * @var Decider
     */
    private $featureSwitch;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->quoteMock = $this->getMockBuilder(BackendQuote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBillingAddress'])
            ->getMock();

        $this->addressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCustomerId'])
            ->getMock();

        $this->sessionManager = $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getQuote'])
            ->getMock();

        $this->sessionManager->method('getQuote')->willReturn($this->quoteMock);
        $this->customerCreditCardCollectionFactoryMock = $this->getMockBuilder(CustomerCreditCardCollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create', 'getCreditCardInfosByCustomerId','addFilter'])
            ->getMock();

        $this->featureSwitch = $this->getMockBuilder(Decider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isAdminReorderForLoggedInCustomerFeatureEnabled'])
            ->getMock();
    }

    private function initCurrentMock()
    {
        $this->block = $this->getMockBuilder(BlockForm::class)
            ->setMethods(['getQuoteData'])
            ->setConstructorArgs(
                [
                    $this->contextMock,
                    $this->configHelperMock,
                    $this->sessionManager,
                    $this->customerCreditCardCollectionFactoryMock,
                    $this->featureSwitch
                ]
            )
            ->getMock();
    }

    /**
     * @test
     * @param $data
     * @dataProvider providerGetCustomerCreditCardInfo
     */
    public function getCustomerCreditCardInfo($data)
    {
        $this->addressMock->expects(self::once())->method('getCustomerId')->willReturn($data['customer_id']);
        $this->quoteMock->expects(self::once())->method('getBillingAddress')->willReturn($this->addressMock);
        $this->block->expects(self::once())->method('getQuoteData')->willReturn($this->quoteMock);

        $this->customerCreditCardCollectionFactoryMock->expects(static::any())->method('create')->willReturnSelf();
        ;
        $this->customerCreditCardCollectionFactoryMock->expects(static::any())
                ->method('getCreditCardInfosByCustomerId')
                ->with($data['customer_id'])
                ->willReturn([new \Magento\Framework\DataObject()]);

        $result = $this->block->getCustomerCreditCardInfo();
        $this->assertEquals($data['expected'], $result);
    }

    public function providerGetCustomerCreditCardInfo()
    {
        return [
            ['data' => [
                'customer_id' => '1111',
                'expected' => [new \Magento\Framework\DataObject()],
                ]
            ],
            ['data' => [
                'customer_id' => '',
                'expected' => false,
                ]
            ],
        ];
    }

    /**
     * @test
     */
    public function isAdminReorderForLoggedInCustomerFeatureEnabled()
    {
        $this->featureSwitch->expects(static::once())->method('isAdminReorderForLoggedInCustomerFeatureEnabled')->willReturn(true);
        $this->assertTrue($this->block->isAdminReorderForLoggedInCustomerFeatureEnabled());
    }

    /**
     * @test
     */
    public function getPublishableKeyBackOfficeShouldReturnConfigValue()
    {
        $this->configHelperMock
            ->method('getPublishableKeyBackOffice')
            ->willReturn("backoffice-key");

        $this->assertEquals("backoffice-key", $this->block->getPublishableKeyBackOffice());
    }
}
