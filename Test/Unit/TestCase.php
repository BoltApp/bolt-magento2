<?php

namespace Bolt\Boltpay\Test\Unit;

use PHPUnit\Framework\TestCase;

class BoltTestCase extends TestCase
{
    private function skipTestInUnitTestsFlow()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            $this->markTestSkipped('Skip integration test in unit test flow');
        }

    }
}