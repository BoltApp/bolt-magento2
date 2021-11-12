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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\GiftOptionsHandler;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManager\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\GiftOptionsHandler
 */
class GiftOptionsHandlerTest extends BoltTestCase
{
    /**
     * @var GiftOptionsHandler
     */
    private $giftOptionsHandler;

    /**
     * @var ObjectManager
     */
    private $objectManager;


    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->giftOptionsHandler = $this->objectManager->create(GiftOptionsHandler::class);
    }

    /**
     * @test
     * @dataProvider handleGiftOptionsDataProvider
     * @covers ::handle
     */
    public function handle($transaction, $comment)
    {
        $order = TestUtils::createDumpyOrder();
        $this->giftOptionsHandler->handle($order, $transaction);
        if ($comment) {
            $commentPrefix = 'BOLTPAY INFO :: gift options';
            self::assertEquals($commentPrefix.$comment, $order->getAllStatusHistory()[0]->getComment());
        } else {
            self::assertEquals([], $order->getAllStatusHistory());
        }
        TestUtils::cleanupSharedFixtures([$order]);
    }

    public function handleGiftOptionsDataProvider()
    {
        $transaction1 = json_decode(
            json_encode(
                [
                    'type' => 'order.create',
                    'order' => [
                        'cart' => [
                            'shipments' => [
                                [
                                    'gift_options' => [
                                        'wrap' => true,
                                        'message' => 'gift message'
                                    ]
                                ]
                            ],
                        ]
                    ],
                ]
            )
        );
        $comment1 = '<br>Gift Wrap: Yes<br>Gift Message: gift message';
        $transaction2 = json_decode(
            json_encode(
                [
                    'type' => 'order.create',
                    'order' => [
                        'cart' => [
                            'shipments' => [
                                [
                                    'gift_options' => [
                                        'wrap' => false,
                                        'message' => 'gift message'
                                    ]
                                ]
                            ],
                        ]
                    ],
                ]
            )
        );
        $comment2 = '<br>Gift Wrap: No<br>Gift Message: gift message';
        $transaction3 = json_decode(
            json_encode(
                [
                    'type' => 'order.create',
                    'order' => [
                        'cart' => [
                            'shipments' => [
                                []
                            ],
                        ]
                    ],
                ]
            )
        );
        $comment3 = '';
        
        return [
            [$transaction1, $comment1],
            [$transaction2, $comment2],
            [$transaction3, $comment3],
        ];
    }
}
