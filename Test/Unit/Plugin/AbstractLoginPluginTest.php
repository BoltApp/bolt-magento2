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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\AbstractLoginPlugin;
use Magento\Checkout\Model\Session as CheckoutSession;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Bolt\Boltpay\Helper\Config;
use Magento\Customer\Controller\Ajax\Login;
use Zend\Stdlib\Parameters;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\AbstractLoginPlugin
 */
class AbstractLoginPluginTest extends BoltTestCase
{
    /**
     * @var AbstractLoginPlugin
     */
    private $abstractLoginPlugin;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    private $objectManager;
    
    /**
     * @var Config
     */
    private $configHelper;
    
    /**
     * @var StoreManager
     */
    private $storeManager;
    
    private $storeId;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $this->configHelper = $this->objectManager->create(Config::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $this->storeManager->getStore()->getId();
        $this->abstractLoginPlugin = $this->objectManager->create(\Bolt\Boltpay\Plugin\LoginPlugin::class);
    }

    private function setCustomerAsLoggedIn($email = 'johntest1xx@bolt.com')
    {
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
            "email_address" => $email,
        ]);

        $customerSession = $this->objectManager->create(Session::class);
        $customerSession->setCustomerId($customer->getId());
    }

    /**
     * @test
     * @dataProvider dataProvider_isCustomerLoggedIn
     *
     * @param $isLoggedIn
     * @throws \ReflectionException
     */
    public function isCustomerLoggedIn($isLoggedIn)
    {
        if ($isLoggedIn) {
            $this->setCustomerAsLoggedIn();
        }
        $this->assertEquals($isLoggedIn, TestHelper::invokeMethod($this->abstractLoginPlugin, 'isCustomerLoggedIn'));
    }

    public function dataProvider_isCustomerLoggedIn()
    {
        return [
            [false], [true]
        ];
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function hasCart_withQuoteItems()
    {
        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1);
        $quote->save();
        $this->checkoutSession->replaceQuote($quote);

        $reflection = TestHelper::getReflectedClass(get_parent_class($this->abstractLoginPlugin));
        $reflectionProperty = $reflection->getProperty('checkoutSession');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->abstractLoginPlugin, $this->checkoutSession);

        $result = TestHelper::invokeMethod($this->abstractLoginPlugin, 'hasCart');
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function setBoltInitiateCheckout()
    {
        TestHelper::invokeMethod($this->abstractLoginPlugin, 'setBoltInitiateCheckout');
        $this->assertTrue($this->checkoutSession->getBoltInitiateCheckout());
    }

    /**
     * @test
     *
     */
    public function notifyException()
    {
        $bugsnag = $this->createPartialMock(
            Bugsnag::class,
            ['notifyException']
        );
        $bugsnag->expects(self::once())->method('notifyException')->with(new \Exception('test'))->willReturnSelf();
        $reflection = TestHelper::getReflectedClass(get_parent_class($this->abstractLoginPlugin));
        $reflectionProperty = $reflection->getProperty('bugsnag');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->abstractLoginPlugin, $bugsnag);
        TestHelper::invokeMethod($this->abstractLoginPlugin, 'notifyException', [new \Exception('test')]);
    }

    /**
     * @test
     */
    public function shouldRedirectToCartPage_ifShouldDisableRedirectCustomerToCartPageAfterTheyLogInIsFalse_willReturnFalse()
    {
        $login = $this->objectManager->create(Login::class);
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_IF_SHOULD_DISABLE_REDIRECT_CUSTOMER_TO_CART_PAGE_AFTER_THEY_LOG_IN, true);
        $this->assertFalse(TestHelper::invokeMethod($this->abstractLoginPlugin, 'shouldRedirectToCartPage', [$login]));
        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }

    /**
     * @test
     */
    public function shouldRedirectToCartPage_willReturnTrue()
    {
        $login = $this->objectManager->create(Login::class);
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_IF_SHOULD_DISABLE_REDIRECT_CUSTOMER_TO_CART_PAGE_AFTER_THEY_LOG_IN, false);
        $this->setCustomerAsLoggedIn('johnmc@bolt.com');

        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1);
        $quote->save();
        $this->checkoutSession->replaceQuote($quote);
        
        $configData = [
            [
                'path' => Config::XML_PATH_ACTIVE,
                'value' => true,
                'scope' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ]
        ];
        TestUtils::setupBoltConfig($configData);
        
        $parameters = $this->objectManager->create(Parameters::class);
        $parameters->set('HTTP_REFERER', $this->storeManager->getStore()->getUrl('test'));
        $login->getRequest()->setServer($parameters);

        $reflection = TestHelper::getReflectedClass(get_parent_class($this->abstractLoginPlugin));
        $reflectionProperty = $reflection->getProperty('checkoutSession');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->abstractLoginPlugin, $this->checkoutSession);
        $this->assertTrue(TestHelper::invokeMethod($this->abstractLoginPlugin, 'shouldRedirectToCartPage', [$login]));

        TestUtils::cleanupFeatureSwitch($featureSwitch);
    }
}
