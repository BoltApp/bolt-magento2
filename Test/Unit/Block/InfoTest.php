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

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\Info;
use Bolt\Boltpay\Test\Unit\TestHelper;

class InfoTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Info
     */
    protected $mock;

    protected function setUp()
    {
        $this->mock = $this->createPartialMock(Info::class, ['getInfo', 'getCcType', 'getCcLast4']);
    }

    /**
     * @test
     */
    public function prepareSpecificInformation()
    {
        $this->mock->expects(self::once())->method('getInfo')->willReturnSelf();
        $this->mock->expects(self::once())->method('getCcType')->willReturn('visa');
        $this->mock->expects(self::once())->method('getCcLast4')->willReturn('1111');
        $data = TestHelper::invokeMethod($this->mock, '_prepareSpecificInformation', [null]);
        $this->assertEquals(
            [
                'Credit Card Type' => 'VISA',
                'Credit Card Number' => 'xxxx-1111'
            ], $data->getData()
        );
    }
}
