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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\ShippingTaxContext;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Bolt\Boltpay\Api\Data\TaxDataInterfaceFactory;
use Bolt\Boltpay\Api\Data\TaxResultInterfaceFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\Tax;
use Bolt\Boltpay\Helper\Discount as DiscountHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

/**
 * Class TaxTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Tax
 */
class TaxTest extends BoltTestCase
{
    const CURRENCY_CODE = 'USD';
    const IMMUTABLE_QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const INCREMENT_ID = 100050001;
    const STORE_ID = 1;

    /**
     * @var TaxDataInterfaceFactory|MockObject
     */
    protected $taxDataFactory;

    /**
     * @var TaxResultInterfaceFactory|MockObject
     */
    protected $taxResultFactory;

    /**
     * @var TotalsInformationManagementInterface|MockObject
     */
    protected $totalsInformationManagement;

    /**
     * @var ShippingTaxContext|MockObject
     */
    protected $shippingTaxContext;

    /**
     * @var TotalsInformationInterface|MockObject
     */
    protected $addressInformation;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Tax
     */
    private $tax;
    
    /** array of objects we need to delete after test */
    private $objectsToClean;

    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->tax = $this->objectManager->create(Tax::class);
        $this->objectsToClean = [];
    }
    
    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures($this->objectsToClean);
    }

    /**
     * @test
     * that setAddressInformation would return set shipping method code and set shipping carrier code
     *
     * @dataProvider provider_setAddressInformation_happyPath
     * @covers ::setAddressInformation
     * @param $shippingReference
     * @param $carrierCode
     * @param $methodCode
     *
     */
    public function setAddressInformation_happyPath($shippingReference, $carrierCode, $methodCode)
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'

        ];

        $shipping_option = [
            'reference' => $shippingReference
        ];
        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);

        $quote = TestUtils::createQuote();
        TestHelper::setProperty($this->tax, 'addressInformation', $addressInformation);
        TestHelper::setProperty($this->tax, 'quote', $quote);
        $this->tax->setAddressInformation($addressData, $shipping_option, null);
        self::assertEquals($carrierCode, TestHelper::getProperty($this->tax, 'addressInformation')->getShippingCarrierCode());
        self::assertEquals($methodCode, TestHelper::getProperty($this->tax, 'addressInformation')->getShippingMethodCode());
    }

    public function provider_setAddressInformation_happyPath()
    {
        return [
            ['carrierCode_methodCode', 'carrierCode', 'methodCode'],
            ['shqshared_GROUND_HOME_DELIVERY', 'shqshared', 'GROUND_HOME_DELIVERY']
        ];
    }

    /**
     * @test
     * that setAddressInformation would return null if no shipping option was specified
     *
     * @covers ::setAddressInformation
     */
    public function setAddressInformation_noShippingOption()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'

        ];

        $shipping_option = null;
        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);

        $quote = TestUtils::createQuote();
        TestHelper::setProperty($this->tax, 'addressInformation', $addressInformation);
        TestHelper::setProperty($this->tax, 'quote', $quote);
        $this->assertNull($this->tax->setAddressInformation($addressData, $shipping_option, null));
    }

    /**
     * @test
     * that createTaxResult would return tax result interface instance
     *
     * @covers ::createTaxResult
     */
    public function createTaxResult()
    {
        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);
        $addressInformation->setTaxAmount(10);
        $taxResult = new \Bolt\Boltpay\Model\Api\Data\TaxResult();
        $taxResult->setSubtotalAmount(1000);

        $this->assertEquals(
            $taxResult,
            $this->tax->createTaxResult($addressInformation, self::CURRENCY_CODE)
        );
    }

    /**
     * @test
     * that createShippingOption would return shipping option interface instance
     *
     * @covers ::createShippingOption
     */
    public function createShippingOption()
    {
        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);
        $addressInformation->setShippingTaxAmount(10);
        $addressInformation->setShippingAmount(10);
        $addressInformation->setShippingDiscountAmount(5);
        
        $priceHelper = $this->objectManager->create(PriceHelper::class);
        TestHelper::setProperty($this->tax, 'priceHelper', $priceHelper);
        
        $quote = TestUtils::createQuote();
        TestHelper::setProperty($this->tax, 'quote', $quote);

        $shipping_option = [
            'service' => 'carrierTitle - methodTitle',
            'reference' => 'carrierCode_methodCode'
        ];

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService(html_entity_decode('carrierTitle - methodTitle [$5.00&nbsp;discount]'))
            ->setCost(500)
            ->setReference('carrierCode_methodCode')
            ->setTaxAmount(1000);

        $this->assertEquals(
            $shippingOptionData,
            $this->tax->createShippingOption(
                $addressInformation,
                self::CURRENCY_CODE,
                $shipping_option
            )
        );
    }

    /**
     * @test
     * that createShippingOption would return "empty" shipping option,
     * with zero cost and tax amount and without service and reference,
     * if no $shipping_option input is specified
     *
     * @covers ::createShippingOption
     */
    public function createShippingOption_noShippingOption()
    {
        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);
        $addressInformation->setShippingDiscountAmount(0);
        $addressInformation->setShippingTaxAmount(0);
        $addressInformation->setShippingAmount(0);
        $quote = TestUtils::createQuote();
        TestHelper::setProperty($this->tax, 'quote', $quote);
        $shipping_option = null;
        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService(null)
            ->setCost(0)
            ->setReference(null)
            ->setTaxAmount(0);

        $this->assertEquals(
            $shippingOptionData,
            $this->tax->createShippingOption(
                $addressInformation,
                self::CURRENCY_CODE,
                $shipping_option
            )
        );
    }

    /**
     * @test
     * that generateResult would return tax data interface instance
     *
     * @covers ::generateResult
     */
    public function generateResult()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];

        $shipping_option = [
            'service' => 'carrierTitle - methodTitle',
            'reference' => 'carrierCode_methodCode'
        ];

        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);
        $addressInformation->setShippingTaxAmount(10);
        $addressInformation->setShippingAmount(10);
        $addressInformation->setTaxAmount(10);

        $totalsInformationManagementInterface = $this->getMockBuilder(\Magento\Checkout\Api\TotalsInformationManagementInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['calculate'])
            ->getMock();
        $totalsInformationManagementInterface->method('calculate')->willReturn($addressInformation);

        $apiHelperProperty = new \ReflectionProperty(
            Tax::class,
            'totalsInformationManagement'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($this->tax, $totalsInformationManagementInterface);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('carrierTitle - methodTitle')
            ->setCost(1000)
            ->setReference('carrierCode_methodCode')
            ->setTaxAmount(1000);

        $taxResult = new \Bolt\Boltpay\Model\Api\Data\TaxResult();
        $taxResult->setSubtotalAmount(0);

        $taxData = new \Bolt\Boltpay\Model\Api\Data\TaxData();
        $taxData
            ->setTaxResult($taxResult)
            ->setShippingOption($shippingOptionData);

        $quote = TestUtils::createQuote();
        TestHelper::setProperty($this->tax, 'quote', $quote);
        TestHelper::setProperty($this->tax, 'addressInformation', $addressInformation);

        $this->assertEquals($taxData, $this->tax->generateResult($addressData, $shipping_option, null));
    }
    
    /**
     * @test
     * that generateResult for virtual quote would return tax data interface instance
     *
     * @covers ::generateResult
     */
    public function generateResult_virtualQuote()
    {
        $addressData = [
            'region' => 'California',
            'country_code' => 'US',
            'postal_code' => '90210',
            'locality' => 'San Franciso',
            'street_address1' => '123 Sesame St.',
            'email' => 'integration@bolt.com',
            'company' => 'Bolt'
        ];

        $shipping_option = [
            'service' => 'No Shipping Required',
            'reference' => 'noshipping'
        ];

        $addressInformation = $this->objectManager->create(TotalsInformationInterface::class);
        $addressInformation->setShippingTaxAmount(10);
        $addressInformation->setShippingAmount(0);
        $addressInformation->setTaxAmount(10);

        $totalsInformationManagementInterface = $this->getMockBuilder(\Magento\Checkout\Api\TotalsInformationManagementInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['calculate'])
            ->getMock();
        $totalsInformationManagementInterface->method('calculate')->willReturn($addressInformation);

        $apiHelperProperty = new \ReflectionProperty(
            Tax::class,
            'totalsInformationManagement'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($this->tax, $totalsInformationManagementInterface);

        $shippingOptionData = new \Bolt\Boltpay\Model\Api\Data\ShippingOption();
        $shippingOptionData
            ->setService('No Shipping Required')
            ->setCost(0)
            ->setReference('noshipping')
            ->setTaxAmount(1000);

        $taxResult = new \Bolt\Boltpay\Model\Api\Data\TaxResult();
        $taxResult->setSubtotalAmount(0);

        $taxData = new \Bolt\Boltpay\Model\Api\Data\TaxData();
        $taxData
            ->setTaxResult($taxResult)
            ->setShippingOption($shippingOptionData);

        $quote = TestUtils::createQuote();
        $product = TestUtils::createVirtualProduct();
        $this->objectsToClean[] = $product;
        $quote->addProduct($product, 1);
        $quote->setIsVirtual(true);
        $quote->save();
        TestHelper::setProperty($this->tax, 'quote', $quote);
        TestHelper::setProperty($this->tax, 'addressInformation', $addressInformation);

        $this->assertEquals($taxData, $this->tax->generateResult($addressData, $shipping_option, null));
    }
}
