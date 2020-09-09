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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Mageplaza;

use Bolt\Boltpay\ThirdPartyModules\Mageplaza\ShippingRestriction;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Registry;

/**
 * Class ShippingRestrictionTest
 * @package Bolt\Boltpay\Test\Unit\ThirdPartyModules\Mageplaza
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Mageplaza\ShippingRestriction
 */
class ShippingRestrictionTest extends TestCase
{
    const QUOTE_ID = 1;

    /** @var Registry */
    private $coreRegistry;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Quote\Address
     */
    private $quoteAddress;

    /**
     * @var ShippingRestriction
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->coreRegistry = $this->quote = $this->createPartialMock(Registry::class, ['register']);
        $this->quote = $this->createPartialMock(Quote::class, [
            'getBoltParentQuoteId',
            'getShippingAddress'
        ]);
        $this->quoteAddress = $this->createMock(Quote\Address::class);
        $this->currentMock = (new ObjectManager($this))->getObject(
            ShippingRestriction::class,
            [
                'coreRegistry' => $this->coreRegistry
            ]
        );
    }

    /**
     * @test
     * @covers ::afterLoadSession
     */
    public function afterLoadSession()
    {
        $this->quote->expects(self::once())->method('getBoltParentQuoteId')->willReturn(self::QUOTE_ID);
        $this->quote->expects(self::once())->method('getShippingAddress')->willReturn($this->quoteAddress);
        $this->coreRegistry->expects(self::exactly(2))->method('register')
            ->withConsecutive(
                ['mp_shippingrestriction_cart', self::QUOTE_ID],
                ['mp_shippingrestriction_address', $this->quoteAddress]
            );

        $this->currentMock->afterLoadSession($this->quote);
    }
}