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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass CustomerBalance
 */
class CustomerBalanceTest extends BoltTestCase
{

    /**
     * @var Config|MockObject
     */
    private $configHelperMock;

    /**
     * @var CustomerBalance|MockObject
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->currentMock = $this->getMockBuilder(CustomerBalance::class)
            ->setConstructorArgs(
                [
                    $this->configHelperMock
                ]
            )
            ->setMethods(null)
            ->getMock();
    }

    /**
     * @test
     * that constructor sets the expected internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $instance = new CustomerBalance($this->configHelperMock);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
    }

    /**
     * @test
     * that filterProcessLayout will not add third party layout if Magento EE Customer Balance on Shopping Cart
     * is not enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_notEnabledInConfig_doesNotAddLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useStoreCreditConfig')->willReturn(false);
        static::assertEquals([], $this->currentMock->filterProcessLayout([]));
    }

    /**
     * @test
     * that collectCartDiscountJsLayout adds Magento EE Customer Balance button layout
     * if enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_ifEnabledInConfig_addsModuleSpecificLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useStoreCreditConfig')->willReturn(true);
        $result = $this->currentMock->filterProcessLayout([]);
        static::assertEquals(
            [
                'component' => 'Magento_CustomerBalance/js/view/payment/customer-balance'
            ],
            $result['components']['block-totals']['children']['storeCredit']
        );
    }
}
