<?php

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\LogRetriever;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsstream;

class LogRetrieverTest extends TestCase
{
    protected function setUp()
    {
        $fileReadResult = "Line 1 of log\nLine 2 of log\nLine 3 of log";
        vfsStreamWrapper::register();
    }

    /**
     * @test
     */
    public function getLog_success()
    {
        $this->assertTrue(true);
    }
}
