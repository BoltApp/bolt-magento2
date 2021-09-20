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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Helper\FeatureSwitch\Definitions;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\LoginPostPlugin;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Controller\Account\LoginPost;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;

/**
 * Class LoginPostPluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\GiftCard
 * @coversDefaultClass \Bolt\Boltpay\Plugin\LoginPostPlugin
 */
class LoginPostPluginTest extends BoltTestCase
{
    /**
     * @var LoginPostPlugin
     */
    protected $plugin;

    protected $objectManager;

    /**
     * @var LoginPost
     */
    protected $loginPost;
    protected $checkoutSession;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(LoginPostPlugin::class);
        $this->loginPost = $this->objectManager->create(LoginPost::class);
        $this->checkoutSession = $this->objectManager->create(CheckoutSession::class);
    }

    /**
     * @test
     * @covers ::afterExecute
     */
    public function afterExecute_ifShouldRedirectToCartPageIsFalse()
    {
        self::assertNull($this->plugin->afterExecute($this->loginPost, null));
    }

    /**
     * @test
     * @covers ::afterExecute
     */
    public function afterExecute_ifShouldRedirectToCartPageIsTrue()
    {
        $featureSwitch = TestUtils::saveFeatureSwitch(Definitions::M2_IF_SHOULD_DISABLE_REDIRECT_CUSTOMER_TO_CART_PAGE_AFTER_THEY_LOG_IN, false);
        $this->setCustomerAsLoggedIn('johnmc@bolt.com');

        $quote = TestUtils::createQuote();
        $product = TestUtils::getSimpleProduct();
        $quote->addProduct($product, 1);
        $quote->save();
        $this->checkoutSession->replaceQuote($quote);

        $reflection = TestHelper::getReflectedClass(get_parent_class($this->plugin));
        $reflectionProperty = $reflection->getProperty('checkoutSession');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->plugin, $this->checkoutSession);

        $this->plugin->afterExecute($this->loginPost, null);
        self::assertTrue($this->checkoutSession->getBoltInitiateCheckout());
        TestUtils::cleanupFeatureSwitch($featureSwitch);
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
     * @covers ::afterExecute
     */
    public function afterExecute_throwException()
    {
        $plugin = $this->createPartialMock(LoginPostPlugin::class,[ 'shouldRedirectToCartPage',
            'notifyException']);

        $expected = new \Exception('General Exception');
        $plugin->expects(self::once())->method('shouldRedirectToCartPage')->willThrowException($expected);

        $plugin->expects(self::once())->method('notifyException')
            ->with($expected)->willReturnSelf();

        $plugin->afterExecute($this->loginPost, null);
    }
}
