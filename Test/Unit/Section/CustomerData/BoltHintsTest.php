<?php

namespace Bolt\Boltpay\Test\Unit\Section\CustomerData;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Section\CustomerData\BoltHints;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class BoltHintsTest extends TestCase
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var BoltHints
     */

    private $boltHints;

    /**
     * @inheritdoc
     */
    public function setUp()
    {

        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);

        $this->boltHints = (new ObjectManager($this))->getObject(
            BoltHints::class,
            [
                'cartHelper' => $this->cartHelper,
                'configHelper' => $this->configHelper,
            ]
        );
    }

    /**
     * @test
     */
    public function getSectionData_returnEmptyIfPPCdisabled()
    {
        $this->configHelper->expects($this->once())->method("getProductPageCheckoutFlag")->willReturn(false);
        $this->cartHelper->expects($this->never())->method("getHints");

        $result = $this->boltHints->getSectionData();

        $this->assertEquals($result, []);
    }

    /**
     * @test
     */
    public function getSectionData_returnHints()
    {
        $this->configHelper->expects($this->once())->method("getProductPageCheckoutFlag")->willReturn(true);
        $this->cartHelper->expects($this->once())->method("getHints")->with(null, 'product')->willReturn("testHints");

        $result = $this->boltHints->getSectionData();

        $this->assertEquals($result, ["data" => "testHints"]);
    }
}
