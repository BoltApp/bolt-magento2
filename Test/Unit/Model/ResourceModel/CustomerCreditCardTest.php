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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel;

use Bolt\Boltpay\Model\ResourceModel\CustomerCreditCard;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;

class CustomerCreditCardTest extends BoltTestCase
{
    /**
     * @var CustomerCreditCard
     */
    private $customerCreditCard;

    private $objectManager;

    /**
     * Setup for CollectionTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerCreditCard = $this->objectManager->create(CustomerCreditCard::class);
    }

    /**
     * @test
     */
    public function construct()
    {
        self::assertEquals('bolt_customer_credit_cards',$this->customerCreditCard->getMainTable());
        self::assertEquals('id',$this->customerCreditCard->getIdFieldName());
    }
}
