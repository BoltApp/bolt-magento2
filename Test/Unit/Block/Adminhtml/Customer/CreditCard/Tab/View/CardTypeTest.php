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

namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Customer\CreditCard\Tab\View;

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View\CardType;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

class CardTypeTest extends BoltTestCase
{
    const ID = '1110';
    const CONSUMER_ID = '2222';
    const CREDIT_CARD_ID = '4444';
    const CARD_INFO = '{"last4":"4445","display_network":"Visa"}';

    /** @var CardType */
    private $block;
    /** @var ObjectManager */
    private $objectManager;
    /** @var  */
    private $customerCreditCardFactory;

    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->block = $this->objectManager->create(CardType::class);
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
            "email_address" => "johntest11@bolt.com",
        ]);
        $this->customerCreditCardFactory = $this->objectManager->create(CustomerCreditCardFactory::class)->create()
            ->setCustomerId($customer->getId())->setConsumerId(self::CONSUMER_ID)
            ->setCreditCardId(self::CREDIT_CARD_ID)->setCardInfo(self::CARD_INFO)
            ->save();
    }

    /**
     * @test
     */
    public function render()
    {
        $result = $this->block->render($this->customerCreditCardFactory);
        $this->assertEquals('Visa', $result);
        TestUtils::cleanupSharedFixtures([$this->customerCreditCardFactory]);
    }
}
