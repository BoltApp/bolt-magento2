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

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Hints;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class HintsTest
 * @package Bolt\Boltpay\Test\Unit\Controller\Cart
 * @coversDefaultClass \Bolt\Boltpay\Controller\Cart\Hints
 */
class HintsTest extends TestCase
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var Bugsnag */
    private $bugsnag;

    /** @var CartHelper */
    private $cartHelper;

    /** @var Context */
    private $context;

    /** @var Hints */
    private $currentMock;

    public function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->context = $this->createMock(Context::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->resultJsonFactory = $this->createMock(JsonFactory::class);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(Hints::class)
            ->setConstructorArgs([
                $this->context,
                $this->resultJsonFactory,
                $this->bugsnag,
                $this->cartHelper
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Hints(
            $this->context,
            $this->resultJsonFactory,
            $this->bugsnag,
            $this->cartHelper
        );
        
        $this->assertAttributeEquals($this->resultJsonFactory, 'resultJsonFactory', $instance);
        $this->assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        $this->assertAttributeEquals($this->cartHelper, 'cartHelper', $instance);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function hintsExecute()
    {
        $hints = [
            'prefill' => [
                'firstName' => "IntegrationBolt",
                'lastName' => "BoltTest",
                'email' => "integration@bolt.com",
                'phone' => "132 231 1234",
                'addressLine1' => "228 7th Avenue",
                'city' => "New York",
                'city' => 'Los Angeles',
                'state' => 'California',
                'zip' => '90017',
                'country' => 'US',
            ],
            'signed_merchant_user_id' => [
                'merchant_user_id' => '4',
                'signature' => 'A+P9eXitTEukVr8v0Fop2Dp5mzvQED3SD8w/sMBvAxc=',
                'nonce' => '15e70158b9348902001e8d0ed21c18a1',
            ],
        ];
        $expected = ['hints'=>$hints];
        $this->cartHelper->method('getHints')
            ->with(null, 'product')->willReturn($hints);

        $json = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->at(0))
            ->method('setData')
            ->with($expected);
        $this->resultJsonFactory->method('create')
            ->willReturn($json);

        $this->currentMock->execute();
    }

    /**
     * @test
     * @covers ::execute
     */
    public function hintsExecute_throwException()
    {
        $expected = [
            'status' => 'failure',
        ];

        $this->cartHelper->method('getHints')
            ->with(null, 'product')->willThrowException(new \Exception('General exception'));

        $json = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $json->expects($this->at(0))
            ->method('setData')
            ->with($expected);
        $this->resultJsonFactory->method('create')
            ->willReturn($json);

        $this->currentMock->execute();
    }
}
