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

namespace Bolt\Boltpay\Test\Unit\Block\Customer;

use Bolt\Boltpay\Block\Customer\CreditCard;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Customer\Model\Session;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Data\Form\FormKey;

/**
 * Class CreditCardTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class CreditCardTest extends BoltTestCase
{
    const CUSTOMER_ID = '11111';
    const PAGE_SIZE = '1';
    const CURRENT_PAGE = '1';
    const FORM_KEY = 'KSQ27m2S1oBVJecR';
    const CONSUMER_ID = '11321';
    const CREDIT_CARD_ID = '11431';
    const CARD_INFO = '{"id":"CAfe9tP97CMXs1","last4":"1114","display_network":"Visa"}';

    /**
     * @var CreditCard
     */
    private $block;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var CustomerCreditCardFactory
     */
    private $customerCreditCardFactory;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
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
            "email_address" => "johntest12222@bolt.com",
        ]);
        $this->customerCreditCardFactory = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->setCustomerId($customer->getId())->setConsumerId(self::CONSUMER_ID)
            ->setCreditCardId(self::CREDIT_CARD_ID)->setCardInfo(self::CARD_INFO)
            ->save();
        $customerSession = $this->objectManager->create(Session::class);
        $customerSession->setCustomerId($customer->getId());
        $this->block = $this->objectManager->create(CreditCard::class);
    }

    protected function tearDownInternal()
    {
        TestUtils::cleanupSharedFixtures([$this->customerCreditCardFactory]);
    }

    /**
     * @test
     */
    public function getCreditCardCollection()
    {
        $this->assertSame(1, $this->block->getCreditCardCollection()->getSize());
    }

    /**
     * @test
     */
    public function getFormKey()
    {
        $formKey = $this->createPartialMock(FormKey::class, ['getFormKey']);
        $formKey->method('getFormKey')->willReturn('form_key');

        TestHelper::setInaccessibleProperty($this->block, 'formKey', $formKey);
        self::assertEquals('form_key', $this->block->getFormKey());

    }
}
