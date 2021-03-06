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

namespace Bolt\Boltpay\Test\Unit\Block\Adminhtml\Customer\CreditCard\Tab\View;

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View\CardNumber;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * Class CardNumber
 * @package Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View
 */
class CardNumberTest extends BoltTestCase
{
    const LAST_4_DIGIT_CARD = 'XXXX-4444';

    /** @var CardNumber */
    private $block;

    protected function setUpInternal()
    {
        $this->block = $this->getMockBuilder(CardNumber::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCardLast4Digit'])
            ->getMock();
    }

    /**
     * @test
     */
    public function render()
    {
        $this->block->expects(self::once())
            ->method('getCardLast4Digit')
            ->willReturn(self::LAST_4_DIGIT_CARD);
        $result = $this->block->render($this->block);

        $this->assertEquals(self::LAST_4_DIGIT_CARD, $result);
    }
}
