<?php

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
        $this->logRetriever = $this->getMockBuilder(LogRetriever::class);
    }

    /**
     * @test
     */
    public function getLog_success()
    {
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function getOneLineOfLog()
    {
        $expected = array(["Line 3 of log"]);

        $this->assertEquals($expected, $this->logRetriever->getExceptionLog($this->virtualLogPath, 1));
    }
}
