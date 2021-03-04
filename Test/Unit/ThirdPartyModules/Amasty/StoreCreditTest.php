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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\ThirdPartyModules\Amasty\StoreCredit;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass StoreCredit
 */
class StoreCreditTest extends BoltTestCase
{

    /**
     * @var Discount|MockObject
     */
    private $discountHelperMock;

    /**
     * @var Bugsnag|MockObject
     */
    private $bugsnagHelperMock;

    /**
     * @var Config|MockObject
     */
    private $configHelperMock;

    /**
     * @var StoreCredit|MockObject
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->discountHelperMock = $this->createPartialMock(Discount::class, []);
        $this->bugsnagHelperMock = $this->createMock(Bugsnag::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->currentMock = $this->getMockBuilder(StoreCredit::class)
            ->setConstructorArgs(
                [
                    $this->discountHelperMock,
                    $this->bugsnagHelperMock,
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
        $instance = new StoreCredit($this->discountHelperMock, $this->bugsnagHelperMock, $this->configHelperMock);
        static::assertAttributeEquals($this->discountHelperMock, 'discountHelper', $instance);
        static::assertAttributeEquals($this->bugsnagHelperMock, 'bugsnagHelper', $instance);
        static::assertAttributeEquals($this->configHelperMock, 'configHelper', $instance);
    }

    /**
     * @test
     * that filterProcessLayout will not add layout if Amasty Store Credit on Shopping Cart
     * is not enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_notEnabledInConfig_doesNotAddLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useAmastyStoreCreditConfig')->willReturn(false);
        static::assertEquals([], $this->currentMock->filterProcessLayout([]));
    }

    /**
     * @test
     * that filterProcessLayout adds Amasty Store Credit total and button layout if enabled in the config
     *
     * @covers ::filterProcessLayout
     */
    public function filterProcessLayout_ifEnabledInConfig_addsModuleSpecificLayout()
    {
        $this->configHelperMock->expects(static::once())->method('useAmastyStoreCreditConfig')->willReturn(true);
        $result = $this->currentMock->filterProcessLayout([]);
        static::assertEquals(
            [
                'component' => 'Amasty_StoreCredit/js/view/checkout/totals/store-credit',
                'sortOrder' => '90'
            ],
            $result['components']['block-totals']['children']['amstorecredit_total']
        );
        static::assertEquals(
            [
                'component' => 'Amasty_StoreCredit/js/view/checkout/payment/store-credit'
            ],
            $result['components']['block-totals']['children']['amstorecredit_form']
        );
    }
}
