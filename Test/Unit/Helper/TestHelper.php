<?php

namespace Bolt\Boltpay\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;

class TestHelper extends TestCase
{

    /**
     * Call protected/private method of a class.
     *
     * @param $object &$object   Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}


