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

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View\CreditCard;

class CreditCardTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CreditCard
     */
    private $block;

    protected function setUp()
    {
        $this->block = $this->createMock(CreditCard::class);
    }

    /**
     * @test
     */
    public function _prepareColumns()
    {
        $this->block->expects(self::exactly(4))
            ->method('addColumn')
            ->withAnyParameters()
            ->willReturnSelf();

        $testMethod = new \ReflectionMethod(CreditCard::class, '_prepareColumns');
        $testMethod->setAccessible(true);
        $testMethod->invokeArgs($this->block, []);
    }
}
