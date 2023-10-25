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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Plugin;

use Bolt\Boltpay\Helper\Config as HelperConfig;
use Bolt\Boltpay\Plugin\ClearQuote;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class QuotePluginTest
 * @package Bolt\Boltpay\Test\Unit\Plugin
 * @coversDefaultClass \Bolt\Boltpay\Plugin\ClearQuote
 */
class ClearQuoteTest extends BoltTestCase
{
    /**
     * @var ClearQuote
     */
    protected $plugin;

    protected $objectManager;

    public function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->plugin = $this->objectManager->create(ClearQuote::class);
    }

    /**
     * @test
     * @covers ::beforeClearQuote
     * @throws \Exception
     */
    public function afterClearQuote()
    {
        TestUtils::setupBoltConfig(
            [
                [
                    'path'    => \Bolt\Boltpay\Helper\Config::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                    'value'   => true,
                    'scope'   => ScopeInterface::SCOPE_STORE,
                    'scopeId' => 0,
                ]
            ]
        );
        $checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $quote = TestUtils::createQuote();
        TestHelper::setInaccessibleProperty($this->plugin,'quoteToRestore', $quote);
        $this->plugin->afterClearQuote($checkoutSession);
        self::assertEquals($quote->getId(), $checkoutSession->getQuoteId());
    }

    /**
     * @test
     * @dataProvider provider_beforeClearQuote_returnNull
     * @covers ::beforeClearQuote
     * @param $currentQuoteId
     * @param $orderQuoteId
     * @throws \Exception
     */
    public function beforeClearQuote_withGetQuoteByIdIsNotCalled_returnNull($currentQuoteId, $orderQuoteId)
    {
        TestUtils::setupBoltConfig(
            [
                [
                    'path'    => \Bolt\Boltpay\Helper\Config::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                    'value'   => true,
                    'scope'   => ScopeInterface::SCOPE_STORE,
                    'scopeId' => 0,
                ]
            ]
        );

        $checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $checkoutSession->getQuote()->setId($currentQuoteId);
        $checkoutSession->setLastSuccessQuoteId($orderQuoteId);

        $this->assertNull($this->plugin->beforeClearQuote($checkoutSession));
        $this->assertNull( TestHelper::getProperty($this->plugin,'quoteToRestore'));
    }

    public function provider_beforeClearQuote_returnNull()
    {
        return [
            ['1', null],
            [null, '1'],
            ['1', '1']
        ];
    }

    /**
     * @test
     * @covers ::beforeClearQuote
     * @throws \Exception
     */
    public function beforeClearQuote_withGetQuoteByIdIsCalled_returnNull()
    {
        TestUtils::setupBoltConfig(
            [
                [
                    'path'    => \Bolt\Boltpay\Helper\Config::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                    'value'   => true,
                    'scope'   => ScopeInterface::SCOPE_STORE,
                    'scopeId' => 0,
                ]
            ]
        );

        $sessionQuote = TestUtils::createQuote();
        /** @var CheckoutSession $checkoutSession */
        $checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $checkoutSession->replaceQuote($sessionQuote);

        $quoteSuccess = TestUtils::createQuote();
        $quoteSuccess->setBoltParentQuoteId($quoteSuccess->getId());
        $quoteSuccess->save();
        $checkoutSession->setLastSuccessQuoteId($quoteSuccess->getId());
        $this->assertNull($this->plugin->beforeClearQuote($checkoutSession));
        $this->assertEquals($sessionQuote->getId(), TestHelper::getProperty($this->plugin,'quoteToRestore')->getId());
    }

    /**
     * @test
     * @covers ::beforeClearQuote
     */
    public function beforeClearQuote_ifPpcIsDisabled_returnNull()
    {
        TestUtils::setupBoltConfig(
            [
                [
                    'path'    => \Bolt\Boltpay\Helper\Config::XML_PATH_PRODUCT_PAGE_CHECKOUT,
                    'value'   => false,
                    'scope'   => ScopeInterface::SCOPE_STORE,
                    'scopeId' => 0,
                ]
            ]
        );

        $checkoutSession = $this->objectManager->create(CheckoutSession::class);
        $this->assertNull($this->plugin->beforeClearQuote($checkoutSession));
    }
}
