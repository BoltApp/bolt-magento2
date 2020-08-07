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

    public function setUp()
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
}
