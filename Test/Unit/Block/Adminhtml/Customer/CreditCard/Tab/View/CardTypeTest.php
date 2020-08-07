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

namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Customer\CreditCard\Tab\View;

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View\CardType;

class CardTypeTest extends \PHPUnit\Framework\TestCase
{
    const CARD_TYPE = 'Visa';

    /** @var CardType */
    private $block;

    protected function setUp()
    {
        $this->block = $this->getMockBuilder(CardType::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCardType'])
            ->getMock();
    }

    /**
     * @test
     */
    public function render()
    {
        $this->block->expects(self::once())
            ->method('getCardType')
            ->willReturn(self::CARD_TYPE);
        $result = $this->block->render($this->block);

        $this->assertEquals(self::CARD_TYPE, $result);
    }
}
