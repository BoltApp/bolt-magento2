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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\LogRetriever;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

/**
 * Class LogRetrieverTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 */

class LogRetrieverTest extends TestCase
{
    /**
     * @var string
     */
    private $virtualLogPath;

    /**
     * @var LogRetriever
     */
    private $logRetriever;

    /**
     * @var vfsStreamDirectory
     */
    private $root;

    protected function setUp()
    {
        $structure = [
            'log' => [
                'exception.log' => "Line 1 of log\nLine 2 of log\nLine 3 of log"
            ]
        ];
        $this->root = vfsStream::setup('root', null, $structure);
        $this->virtualLogPath = "/log/exception.log";
        $this->logRetriever = $this->getMockBuilder(LogRetriever::class)
            ->setMethods(['getLogs'])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     */
    public function getLogWithoutSpecifiedLines()
    {
        $expected = ['Line 1 of log', 'Line 2 of log', 'Line 3 of log'];

        $result = $this->logRetriever->getLogs(
            $this->root->url() . $this->virtualLogPath
        );

        $this->assertEquals($expected, $result);
    }
    /**
     * @test
     */
    public function getOneLineOfLog()
    {
        $expected = ['Line 3 of log'];

        $result = $this->logRetriever->getLogs(
            $this->root->url() . $this->virtualLogPath,
            1
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function noLogFound()
    {
        $invalidPath = "invalid/path";
        $expected = ['No file found at ' . $this->root->url() . $invalidPath];

        $result = $this->logRetriever->getLogs(
            $this->root->url() . $invalidPath
        );
        $this->assertEquals($expected, $result);
    }
}
