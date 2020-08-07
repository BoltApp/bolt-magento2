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

use Bolt\Boltpay\Block\Adminhtml\Customer\CreditCard\Tab\View\DeleteAction;

class DeleteActionTest extends \PHPUnit\Framework\TestCase
{
    const ID = '1';
    const DELETE_BUTTON_HTML = '<a href="https://www.bolt.com/admin/boltpay/customer/deletecreditcard/id/1/">Delete</a>';

    /** @var DeleteAction */
    private $block;

    protected function setUp()
    {
        $this->block = $this->getMockBuilder(DeleteAction::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId','getUrl','_toLinkHtml'])
            ->getMock();
    }

    /**
     * @test
     */
    public function render()
    {
        $row = new \Magento\Framework\DataObject();
        $row->setId(self::ID);

        $this->block->expects(self::once())
            ->method('getUrl')
            ->with('boltpay/customer/deletecreditcard', ['id' => self::ID])
            ->willReturnSelf();

        $this->block->expects(self::any())
            ->method('_toLinkHtml')
            ->willReturn(self::DELETE_BUTTON_HTML);


        $result = $this->block->render($row);
        $this->assertEquals(self::DELETE_BUTTON_HTML, $result);
    }
}
