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
namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\CustomerCreditCard;

use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

class CollectionTest extends BoltTestCase
{
    const ID = '1110';
    const CONSUMER_ID = '1112';
    const CREDIT_CARD_ID = '1113';
    const CARD_INFO = '{"last4":"1111","display_network":"Visa"}';

    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard\Collection
     */
    private $customerCreditCardCollection;

    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCardFactory
     */
    private $customerCreditCard;

    private $objectManager;

    private $storeId;

    private $websiteId;


    /**
     * Setup for CollectionTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerCreditCardCollection = $this->objectManager->create(Collection::class);
        $this->customerCreditCard = $this->objectManager->create(CustomerCreditCardFactory::class);
        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();
    }

    /**
     * @test
     */
    public function getCreditCardInfosByCustomerId()
    {
        $customer = TestUtils::createCustomer($this->websiteId, $this->storeId, [
            "street_address1" => "street",
            "street_address2" => "",
            "locality"        => "Los Angeles",
            "region"          => "California",
            'region_code'     => 'CA',
            'region_id'       => '12',
            "postal_code"     => "11111",
            "country_code"    => "US",
            "country"         => "United States",
            "name"            => "lastname firstname",
            "first_name"      => "firstname",
            "last_name"       => "lastname",
            "phone_number"    => "11111111",
            "email_address"   => "john@bolt.com",
        ]);
        $customerId = $customer->getId();
        $customerCreditCard = $this->customerCreditCard->create()->saveCreditCard(
            $customerId,
            self::CONSUMER_ID,
            self::CREDIT_CARD_ID,
            []
        );
        $result = $this->customerCreditCardCollection->getCreditCardInfosByCustomerId($customerId);
        $this->assertEquals($customerCreditCard->getId(), $result->getFirstItem()->getId());
    }
}
