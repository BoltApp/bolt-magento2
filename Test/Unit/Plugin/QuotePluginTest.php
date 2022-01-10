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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Plugin\QuotePlugin;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class QuotePluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\QuotePlugin
 */
class QuotePluginTest extends BoltTestCase
{
    /**
     * @var Quote
     */
    protected $subject;

    /**
     * @var QuotePlugin
     */
    protected $plugin;

    /**
     * @var callable
     */
    protected $proceed;

    /** @var callable|MockObject */
    protected $callback;

    private $objectManager;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(QuotePlugin::class);
    }

    /**
     * @test
     * that aroundAfterSave should return proceed if subject have boltParentQuoteId
     * and boltParentQuoteId is not same as getId
     *
     * @covers ::aroundAfterSave
     * @dataProvider dataProviderAroundAfterSave
     *
     * @param $isSameId
     * @param $expectedCall
     */
    public function aroundAfterSave($isSameId, $expectedCall)
    {
        /** @var callable $callback */
        $callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $proceed = function () use ($callback) {
            return $callback();
        };
        $subject = TestUtils::createQuote();
        $subject->setBoltParentQuoteId(($isSameId) ? $subject->getId() : 100);
        $callback->expects($expectedCall)->method('__invoke');
        $this->plugin->aroundAfterSave($subject, $proceed);

    }

    public function dataProviderAroundAfterSave()
    {
        return [
            [true, self::once()],
            [false, self::never()],

        ];
    }

    /**
     * @test
     * that afterValidateMinimumAmount changes result of the original method call only for Bolt backoffice quotes
     *
     * @covers ::afterValidateMinimumAmount
     *
     * @dataProvider afterValidateMinimumAmount_withVariousQuoteStates
     *
     * @param string $boltCheckoutType quote flag
     * @param string $paymentMethod set on quote
     * @param bool $originalResult of the validateMinimumAmount method call
     * @param bool $expectedResult after the plugin method call
     */
    public function afterValidateMinimumAmount_withVariousQuoteStates_overridesResultForBoltBackendOrders(
        $boltCheckoutType,
        $paymentMethod,
        $originalResult,
        $expectedResult
    )
    {
        $subject = TestUtils::createQuote();
        $subject->setBoltCheckoutType($boltCheckoutType);
        $subject->getPayment()->setMethod($paymentMethod);

        static::assertEquals(
            $expectedResult,
            $this->plugin->afterValidateMinimumAmount($subject, $originalResult)
        );
    }

    /**
     * Data provider for {@see afterValidateMinimumAmount_withVariousQuoteStates_overridesResultForBoltBackendOrders}
     *
     * @return array[] containing checkout type, payment method, original and expected result
     */
    public function afterValidateMinimumAmount_withVariousQuoteStates()
    {
        return [
            'Bolt backoffice quote with invalid minimum amount returns true' => [
                'boltCheckoutType' => \Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE,
                'paymentMethod' => \Bolt\Boltpay\Model\Payment::METHOD_CODE,
                'originalResult' => false,
                'expectedResult' => true,
            ],
            'Checkmo quote with invalid minimum amount returns true' => [
                'boltCheckoutType' => \Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_BACKOFFICE,
                'paymentMethod' => \Magento\OfflinePayments\Model\Checkmo::PAYMENT_METHOD_CHECKMO_CODE,
                'originalResult' => false,
                'expectedResult' => false,
            ],
            'Bolt multistep quote with invalid minimum amount returns false' => [
                'boltCheckoutType' => \Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_MULTISTEP,
                'paymentMethod' => \Bolt\Boltpay\Model\Payment::METHOD_CODE,
                'originalResult' => false,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @test
     */
    public function afterGetIsActive_returnTrue()
    {
        $subject = TestUtils::createQuote();
        $subject->setBoltCheckoutType(\Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_PPC);
        self::assertTrue($this->plugin->afterGetIsActive($subject, false));
    }

    /**
     * @test
     */
    public function afterGetIsActive_returnFalse()
    {
        $subject = TestUtils::createQuote();
        $subject->setBoltCheckoutType(\Bolt\Boltpay\Helper\Cart::BOLT_CHECKOUT_TYPE_MULTISTEP);
        self::assertFalse($this->plugin->afterGetIsActive($subject, false));
    }
}
