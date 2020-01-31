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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel;

use Bolt\Boltpay\Model\ResourceModel\Log;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;

class LogTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\ResourceModel\Log
     */
    private $logMock;

    /**
     * Setup for CustomerCreditCardTest Class
     */
    public function setUp()
    {
        $this->logMock = $this->getMockBuilder(Log::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->logMock->expects($this->once())->method('_init')
            ->with('bolt_webhook_log', 'id')
            ->willReturnSelf();

        TestHelper::invokeMethod($this->logMock, '_construct');
    }
}