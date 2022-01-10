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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Form as BlockForm;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class FormTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class FormTest extends BoltTestCase
{
    const CONSUMER_ID = '1132';
    const CREDIT_CARD_ID = '1143';
    const CARD_INFO = '{"id":"CAfe9tP97CMXs","last4":"1111","display_network":"Visa"}';


    /** @var ObjectManager */
    private $objectManager;

    /**
     * @var BlockForm
     */
    private $blockForm;
    private $testAddressData;
    private $quote;
    private $customerCreditCardFactory;

    const EMAIL_ADDRESS = 'integration@bolt.com';

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->blockForm = $this->objectManager->create(BlockForm::class);


        $store = $this->objectManager->get(StoreManagerInterface::class);
        $storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $websiteId = $websiteRepository->get('base')->getId();
        $customer = TestUtils::createCustomer($websiteId, $storeId, [
            "street_address1" => "street",
            "street_address2" => "",
            "locality" => "Los Angeles",
            "region" => "California",
            'region_code' => 'CA',
            'region_id' => '12',
            "postal_code" => "11111",
            "country_code" => "US",
            "country" => "United States",
            "name" => "lastname firstname",
            "first_name" => "firstname",
            "last_name" => "lastname",
            "phone_number" => "11111111",
            "email_address" => "johntest122@bolt.com",
        ]);
        $this->customerCreditCardFactory = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->setCustomerId($customer->getId())->setConsumerId(self::CONSUMER_ID)
            ->setCreditCardId(self::CREDIT_CARD_ID)->setCardInfo(self::CARD_INFO)
            ->save();

        $this->testAddressData = [
            'company'         => "",
            'country'         => "United States",
            'country_code'    => "US",
            'email'           => "johntest122@bolt.com",
            'first_name'      => "IntegrationBolt",
            'last_name'       => "BoltTest",
            'locality'        => "New York",
            'phone'           => "132 231 1234",
            'postal_code'     => "10011",
            'region'          => "New York",
            'street_address1' => "228 7th Avenue",
            'street_address2' => "228 7th Avenue 2",
        ];
        $this->quote = TestUtils::createQuote(['customer_id' => $customer->getId()]);
        TestUtils::setAddressToQuote($this->testAddressData, $this->quote, 'billing');
        $this->quote->getBillingAddress()->setCustomerId($customer->getId());
        TestHelper::setInaccessibleProperty($this->blockForm,'_quote', $this->quote);
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures([$this->customerCreditCardFactory]);
    }

    /**
     * @test
     * @param $data
     */
    public function getCustomerCreditCardInfo()
    {
        $result = $this->blockForm->getCustomerCreditCardInfo();
        $this->assertEquals(1, $result->getSize());
    }

    /**
     * @test
     * that getAdditionalCheckoutButtonAttributes returns additional checkout button attributes
     * stored in additional config field under 'checkoutButtonAttributes' field
     *
     *
     * @dataProvider getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigsProvider
     *
     * @param string $additionalConfig string from config property
     * @param mixed $expectedResult from the tested method
     */
    public function getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigs_returnsButtonAttributes(
        $additionalConfig,
        $expectedResult
    )
    {
        $configData = [
            [
                'path' => Config::XML_PATH_ADDITIONAL_CONFIG,
                'value' => $additionalConfig,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->quote->getStoreId(),
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        $result = $this->blockForm->getAdditionalCheckoutButtonAttributes();
        static::assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for
     *
     * @see getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigs_returnsButtonAttributes
     *
     * @return array[] containing additional config values and expected result of the tested method
     */
    public function getAdditionalCheckoutButtonAttributes_withVariousAdditionalConfigsProvider()
    {
        return [
            'Only attributes in initial config' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {
                        "data-btn-txt": "Pay now"
                    }
                }',
                'expectedResult' => (object)['data-btn-txt' => 'Pay now'],
            ],
            'Multiple attributes' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {
                        "data-btn-txt": "Pay now",
                        "data-btn-text": "Data"
                    }
                }',
                'expectedResult' => (object)['data-btn-txt' => 'Pay now', 'data-btn-text' => 'Data'],
            ],
            'Empty checkout button attributes property' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {}
                }',
                'expectedResult' => (object)[],
            ],
            'Missing checkout button attributes property' => [
                'additionalConfig' => '{
                    "checkoutButtonAttributes": {}
                }',
                'expectedResult' => (object)[],
            ],
            'Invalid additional config JSON' => [
                'additionalConfig' => 'invalid JSON',
                'expectedResult' => (object)[],
            ],
        ];
    }

    /**
     * @test
     */
    public function isAdminReorderForLoggedInCustomerFeatureEnabled()
    {
        $featureSwitch = TestUtils::saveFeatureSwitch(\Bolt\Boltpay\Helper\FeatureSwitch\Definitions::M2_BOLT_ADMIN_REORDER_FOR_LOGGED_IN_CUSTOMER, true);
        $this->assertTrue($this->blockForm->isAdminReorderForLoggedInCustomerFeatureEnabled());
        TestUtils::cleanupSharedFixtures($featureSwitch);
    }

    /**
     * @test
     */
    public function getPublishableKeyBackOfficeShouldReturnConfigValue()
    {
        $configHelperMock = $this->createPartialMock(Config::class,['getPublishableKeyBackOffice']);
        $configHelperMock
            ->method('getPublishableKeyBackOffice')
            ->with($this->quote->getStoreId())
            ->willReturn("backoffice-key");
        TestHelper::setInaccessibleProperty($this->blockForm,'configHelper', $configHelperMock);
        $this->assertEquals("backoffice-key", $this->blockForm->getPublishableKeyBackOffice());
    }
    
    /**
     * @test
     */
    public function getPublishableKeyPaymentOnlyShouldReturnConfigValue()
    {
        $configHelperMock = $this->createPartialMock(Config::class,['getPublishableKeyPayment']);
        $configHelperMock
            ->method('getPublishableKeyPayment')
            ->with($this->quote->getStoreId())
            ->willReturn("payment-key");
        TestHelper::setInaccessibleProperty($this->blockForm,'configHelper', $configHelperMock);
        $this->assertEquals("payment-key", $this->blockForm->getPublishableKeyPaymentOnly());
    }
}
