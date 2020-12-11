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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\ErrorResponse;

/**
 * Class ErrorResponseTest
 * @package Bolt\Boltpay\Test\Unit\Model
 * @coversDefaultClass \Bolt\Boltpay\Model\ErrorResponse
 */
class ErrorResponseTest extends TestCase
{
    /**
     * @var ErrorResponse
     */
    private $currentMock;

    public function setUp(): void
    {
        $this->currentMock = new ErrorResponse();
    }

    /**
     * @test
     *
     * @covers ::prepareErrorMessage
     *
     * @dataProvider providerPrepareErrorMessage
     *
     * @param  $errCode
     * @param  $message
     * @param  $additionalData
     * @param  $expected
     */
    public function prepareErrorMessage($errCode, $message, $additionalData, $expected)
    {
        $result = $this->currentMock->prepareErrorMessage($errCode, $message, $additionalData);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for {@see prepareErrorMessage}
     */
    public function providerPrepareErrorMessage()
    {
        return [
            [
                ErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                [],
                '{"status":"failure","error":{"code":6200,"message":"The order reference is invalid."}}'
            ],
            [
                ErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                ['order_id' => 1],
                '{"status":"failure","error":{"code":6200,"message":"The order reference is invalid."},"order_id":1}'
            ],
        ];
    }
    
    /**
     * @test
     *
     * @covers ::prepareUpdateCartErrorMessage
     *
     * @dataProvider providerPrepareUpdateCartErrorMessage
     *
     * @param  $errCode
     * @param  $message
     * @param  $additionalData
     * @param  $expected
     */
    public function prepareUpdateCartErrorMessage($errCode, $message, $additionalData, $expected)
    {
        $result = $this->currentMock->prepareErrorMessage($errCode, $message, $additionalData);
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Data provider for {@see prepareUpdateCartErrorMessage}
     */
    public function providerPrepareUpdateCartErrorMessage()
    {
        return [
            [
                ErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                [],
                '{"status":"failure","error":{"code":6200,"message":"The order reference is invalid."}}'
            ],
            [
                ErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                [
                    'order_reference' => 100010,
                    'display_id' => '',
                    'currency' => 'USD',
                    'total_amount' => 50500,
                    'tax_amount' => 1000,            
                    'items' => [
                        [
                            'name'         => 'Beaded Long Dress',
                            'description'  => 'Test
New
Lines',
                            'reference'    => 101,
                            'total_amount' => 50000,
                            'unit_price'   => 50000,
                            'quantity'     => 1,
                            'image_url'    => 'https://images.example.com/dress.jpg',
                            'type'         => 'physical',
                            'properties'   =>
                                [
                                    [
                                        'name'  => 'color',
                                        'value' => 'blue',
                                    ],
                                ],
                        ],
                    ],
                    'discounts' => [],
                ],
                '{"status":"failure","error":{"code":6200,"message":"The order reference is invalid."},"order_reference":100010,"display_id":"","currency":"USD","total_amount":50500,"tax_amount":1000,"items":{"0":{"name":"Beaded Long Dress","description":"Test\nNew\nLines","reference":101,"total_amount":50000,"unit_price":50000,"quantity":1,"image_url":"https:\/\/images.example.com\/dress.jpg","type":"physical","properties":{"0":{"name":"color","value":"blue"}}}},"discounts":{}}'
            ],
        ];
    }
}
