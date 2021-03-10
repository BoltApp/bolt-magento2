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
 *
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Section\CustomerData;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Section\CustomerData\BoltHints;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class BoltHintsTest extends BoltTestCase
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
    public function setUpInternal()
    {
        $this->cartHelper = $this->createMock(CartHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);

        $this->boltHints = (new ObjectManager($this))->getObject(
            BoltHints::class,
            [
                'cartHelper'   => $this->cartHelper,
                'configHelper' => $this->configHelper,
            ]
        );
    }

    /**
     * @test
     */
    public function getSectionData_returnEmptyIfPPCdisabled()
    {
        $this->configHelper->expects($this->once())->method('getProductPageCheckoutFlag')->willReturn(false);
        $this->cartHelper->expects($this->never())->method('getHints');

        $result = $this->boltHints->getSectionData();

        $this->assertEquals($result, []);
    }

    /**
     * @test
     */
    public function getSectionData_returnHints()
    {
        $this->configHelper->expects($this->once())->method('getProductPageCheckoutFlag')->willReturn(true);
        $this->cartHelper->expects($this->once())->method('getHints')->with(null, 'product')->willReturn('testHints');

        $result = $this->boltHints->getSectionData();

        $this->assertEquals($result, ['data' => 'testHints']);
    }
}
