<?php

namespace Bolt\Boltpay\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class TestHelper extends TestCase
{
    /**
     * Gets the object reports that reports information about a class.
     * When using Reflection on mocked classes, properties with original names can only be found on parent class
     *
     * @param mixed $class Either a string containing the name of the class to reflect, or an object.
     *
     * @return ReflectionClass  instance of the object used for inspection of the passed class
     * @throws ReflectionException if the class does not exist.
     */
    public static function getReflectedClass($class)
    {
        if (is_subclass_of($class, 'PHPUnit_Framework_MockObject_MockObject')) {
            return new ReflectionClass(get_parent_class($class));
        }
        return new ReflectionClass($class);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeMethod($object, $methodName, array $parameters = [])
    {
        try {
            $reflectedMethod = self::getReflectedClass($object)->getMethod($methodName);
            $reflectedMethod->setAccessible(true);

            return $reflectedMethod->invokeArgs(is_object($object) ? $object : null, $parameters);
        } finally {
            if ($reflectedMethod && ($reflectedMethod->isProtected() || $reflectedMethod->isPrivate())) {
                $reflectedMethod->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to set a private or protected property
     *
     * @param object|string $objectOrClassName The object of the property to be set
     *                                          If the property is static, then a this should be a string of the class name.
     * @param string        $propertyName The name of the property to be set
     * @param mixed         $value The value to be set to the named property
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function setProperty($objectOrClassName, $propertyName, $value)
    {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            if (is_object($objectOrClassName)) {
                $reflectedProperty->setValue($objectOrClassName, $value);
            } else {
                $reflectedProperty->setValue($value);
            }
        } finally {
            if ($reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate())) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to get a private or protected property
     *
     * @param object|string $objectOrClassName The object of the property to get
     *                                          If the property is static, then this should be a string of the class name.
     * @param string        $propertyName The name of the property to get
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function getProperty($objectOrClassName, $propertyName)
    {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            if (is_object($objectOrClassName)) {
                return $reflectedProperty->getValue($objectOrClassName);
            } else {
                return $reflectedProperty->getValue();
            }
        } finally {
            if ($reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate())) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }
}
