<?php

namespace Bolt\Boltpay\Test\Unit\Controller\Cart;

use Bolt\Boltpay\Controller\Cart\Hints;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;

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
            ->with(null,'product')->willReturn($hints);

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
