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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Model\Api\UniversalApi;
use Bolt\Boltpay\Model\Api\CreateOrder;
use Bolt\Boltpay\Model\Api\Shipping;
use Bolt\Boltpay\Model\Api\ShippingMethods;
use Bolt\Boltpay\Model\Api\UpdateCart;
use Bolt\Boltpay\Model\Api\Debug;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Bolt\Boltpay\Model\ErrorResponse;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class UniversalApiTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UniversalApi
 */
class UniversalApiTest extends BoltTestCase
{
    const DATA = ['data1' => 'not important'];

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UniversalApi
     */
    private $universalApi;

    /**
     * @inheritdoc
     */
    protected function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->universalApi = $this->objectManager->create(UniversalApi::class);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function invalidRequestEvent_sendsErrorResponse()
    {
        $invalidType = "invalid_hook_type";
        $this->universalApi->execute($invalidType, self::DATA);

        $response = json_decode(TestHelper::getProperty($this->universalApi, 'response')->getBody(), true);
        $this->assertEquals(
            [
                'status' => 'failure',
                'error' => [
                    'code' => ErrorResponse::ERR_SERVICE,
                    'message' => 'Invalid webhook type invalid_hook_type',
                    ]
            ],
            $response
        );
    }

    /**
     * @test
     */
    public function orderCreation_returnsTrue()
    {
        $event = "order.create";
        $data = ['order' => 'order', 'currency' => 'currency'];
        $createOrder = $this->createMock(CreateOrder::class);
        $createOrder->expects(self::once())->method('execute')->with(
            $event,
            $data['order'],
            $data['currency']
        );
        TestHelper::setProperty($this->universalApi, 'createOrder', $createOrder);

        $this->assertTrue($this->universalApi->execute($event, $data));
    }

    /**
     * @test
     */
    public function updateCart_returnsTrue()
    {
        $event = "cart.update";
        $data = [
            'cart' => 'cart',
            'add_items' => 'add_items',
            'remove_items' => 'remove_items',
            'discount_codes_to_add' => 'discount_codes_to_add',
            'discount_codes_to_remove' => 'discount_codes_to_remove',
        ];

        $updateCart = $this->createMock(UpdateCart::class);
        $updateCart->expects(self::once())->method('execute')->with(
            $data['cart'],
            $data['add_items'],
            $data['remove_items'],
            $data['discount_codes_to_add'],
            $data['discount_codes_to_remove']
        );

        TestHelper::setProperty($this->universalApi, 'updateCart', $updateCart);
        
        $this->assertTrue($this->universalApi->execute($event, $data));
    }

    /**
     * @test
     */
    public function getShippingMethods_returnsTrue()
    {
        $event = "order.shipping_and_tax";
        $data = [
            'cart' => 'cart',
            'shipping_address' => 'shipping_address'
        ];
        $shippingMethods = $this->createMock(ShippingMethods::class);
        $shippingMethods->expects(self::once())
            ->method('getShippingMethods')
            ->with(
                $data['cart'],
                $data['shipping_address']
            );
        TestHelper::setProperty($this->universalApi, 'shippingMethods', $shippingMethods);

        $this->assertTrue($this->universalApi->execute($event, $data));
    }

    /**
     * @test
     */
    public function shippingOptions_returnsTrue()
    {
        $event = "order.shipping";
        $data = [
            'cart' => 'cart',
            'shipping_address' => 'shipping_address',
            'shipping_option' => 'shipping_option'
        ];

        $shipping = $this->createMock(Shipping::class);
        $shipping->expects(self::once())
            ->method('execute')
            ->with(
                $data['cart'],
                $data['shipping_address'],
                $data['shipping_option']
            );

        TestHelper::setProperty($this->universalApi, 'shipping', $shipping);

        $this->assertTrue($this->universalApi->execute($event, $data));
    }

    /**
     * @test
     */
    public function debug_returnsTrue()
    {
        $event = "debug";
        $data = [
            'type' => 'log',
        ];
        $debug = $this->createMock(Debug::class);
        $debug->expects(self::once())
            ->method('universalDebug')
            ->with(
                $data
            );

        TestHelper::setProperty($this->universalApi,'debug', $debug);
        $this->assertTrue($this->universalApi->execute($event, $data));
    }
}
