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
 *
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.boltapp.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit;

use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\Parameters as ParametersRule;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;

require_once __DIR__ . '/_files/absent_types.php';

// phpcs:ignore
class BoltTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->setUpInternal();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownInternal();
    }

    public static function assertAttributeInstanceOf($expected, $attributeName, $classOrObject, $message = '')
    {
        static::assertInstanceOf(
            $expected,
            static::readAttribute($classOrObject, $attributeName),
            $message
        );
    }

    public static function assertAttributeEquals($expected, $actualAttributeName, $actualClassOrObject, $message = '', $delta = 0.0, $maxDepth = 10, $canonicalize = false, $ignoreCase = false)
    {
        static::assertEquals(
            $expected,
            static::readAttribute($actualClassOrObject, $actualAttributeName),
            $message,
            $delta,
            $maxDepth,
            $canonicalize,
            $ignoreCase
        );
    }

    public static function readAttribute($classOrObject, $attributeName)
    {
        if (!\is_string($attributeName)) {
            throw InvalidArgumentHelper::factory(2, 'string');
        }

        if (!\preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $attributeName)) {
            throw InvalidArgumentHelper::factory(2, 'valid attribute name');
        }

        if (\is_string($classOrObject)) {
            if (!\class_exists($classOrObject)) {
                throw InvalidArgumentHelper::factory(
                    1,
                    'class name'
                );
            }

            return static::getStaticAttribute(
                $classOrObject,
                $attributeName
            );
        }

        if (\is_object($classOrObject)) {
            return static::getObjectAttribute(
                $classOrObject,
                $attributeName
            );
        }

        throw InvalidArgumentHelper::factory(
            1,
            'class name or object'
        );
    }

    public static function getObjectAttribute($object, $attributeName)
    {
        if (!\is_object($object)) {
            throw InvalidArgumentHelper::factory(1, 'object');
        }

        if (!\is_string($attributeName)) {
            throw InvalidArgumentHelper::factory(2, 'string');
        }

        if (!\preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $attributeName)) {
            throw InvalidArgumentHelper::factory(2, 'valid attribute name');
        }

        try {
            $attribute = new ReflectionProperty($object, $attributeName);
        } catch (ReflectionException $e) {
            $reflector = new ReflectionObject($object);

            while ($reflector = $reflector->getParentClass()) {
                try {
                    $attribute = $reflector->getProperty($attributeName);
                    break;
                } catch (ReflectionException $e) {
                }
            }
        }

        if (isset($attribute)) {
            if (!$attribute || $attribute->isPublic()) {
                return $object->$attributeName;
            }

            $value = TestHelper::makeAccessible($attribute)->getValue($object);

            return $value;
        }

        throw new Exception(
            \sprintf(
                'Attribute "%s" not found in object.',
                $attributeName
            )
        );
    }

    protected function setUpInternal()
    {
    }

    protected function tearDownInternal()
    {
    }

    /**
     * PHPUnit 9's setMethods() for any PHPUnit from 9 to 12: getMockBuilder($type)->setMethods($methods)
     *
     * setMethods() was removed in PHPUnit 10, and its replacements do not cover how the suite uses it:
     * onlyMethods() rejects methods the type does not declare (DataObject's magic getters and setters,
     * extra methods on an interface), and addMethods(), which accepted them, was removed in PHPUnit 12.
     *
     * Abstract methods, including all of an interface's, are always doubled, since the double cannot be
     * instantiated without them.
     *
     * @param string        $type
     * @param string[]|null $methods as for setMethods(): null doubles none, [] doubles all
     *
     * @return MockBuilder
     */
    protected function getMockBuilderWithMethods($type, $methods = null)
    {
        if ($methods === []) {
            return $this->getMockBuilder($type);
        }
        return $this->getMockBuilderForAbstractClass($type, (array)$methods);
    }

    /**
     * Stand-in for getMockForAbstractClass(), removed in PHPUnit 12: doubles $type's abstract methods and $methods
     *
     * Like Magento 2.4.9's own MockCreationTrait::createPartialMockWithReflection(), this writes the method list
     * onto the builder directly; PHPUnit's generator still doubles methods the type does not declare.
     *
     * @param string   $type
     * @param string[] $methods
     *
     * @return MockBuilder
     */
    protected function getMockBuilderForAbstractClass($type, array $methods = [])
    {
        $builder = $this->getMockBuilder($type);
        if (class_exists($type) || interface_exists($type)) {
            foreach ((new ReflectionClass($type))->getMethods() as $method) {
                if ($method->isAbstract()) {
                    $methods[] = $method->getName();
                }
            }
        }
        if (!$methods) {
            return $builder->onlyMethods([]);
        }
        $unique = [];
        foreach ($methods as $method) {
            $unique[strtolower($method)] = $method;
        }
        // declared on MockBuilder in PHPUnit 9, on its parent TestDoubleBuilder in PHPUnit 12
        TestHelper::makeAccessible(new ReflectionProperty($builder, 'methods'))->setValue($builder, array_values($unique));
        return $builder;
    }

    /**
     * createPartialMock() that, like PHPUnit 9's, also accepts methods $type does not declare
     *
     * @param string   $type
     * @param string[] $methods [] doubles none
     *
     * @return MockObject
     */
    protected function createPartialMockWithMethods($type, array $methods)
    {
        return $this->getMockBuilderWithMethods($type, $methods ?: null)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
    }

    /**
     * Stand-in for withConsecutive(), removed in PHPUnit 10
     *
     * `->with(...self::consecutiveArgs(['a'], ['b', 'c']))` expects ('a') on the first call and ('b', 'c') on the
     * second. As with withConsecutive(), values that are not constraints are compared with equalTo(), and calls
     * beyond the listed ones are not checked.
     *
     * PHPUnit 10.5+ hands a variadic callback every argument of a call. PHPUnit 9 hands a callback one argument
     * and rejects calls with fewer arguments than with() has constraints, so there a single constraint, on the
     * first argument, reads the rest of the call from the invocation PHPUnit is checking.
     *
     * @param array ...$calls the expected arguments of each call, in order
     *
     * @return Constraint[] for with()
     */
    public static function consecutiveArgs(array ...$calls)
    {
        if (!$calls || !max(array_map('count', $calls))) {
            return [];
        }
        $call = 0;
        $check = function (array $actualArgs) use ($calls, &$call) {
            $expectedArgs = isset($calls[$call]) ? $calls[$call] : [];
            $call++;
            foreach ($expectedArgs as $position => $expected) {
                if (!array_key_exists($position, $actualArgs)) {
                    throw new ExpectationFailedException(sprintf('Call %d has no parameter %d.', $call, $position));
                }
                self::assertConsecutiveArg($expected, $actualArgs[$position], $position, $call);
            }
            return true;
        };
        if (method_exists(Callback::class, 'isVariadic')) {
            return [
                static::callback(function (...$actualArgs) use ($check) {
                    return $check($actualArgs);
                })
            ];
        }
        return [
            static::callback(function ($firstArg) use ($check) {
                $actualArgs = self::argsOfInvocationUnderCheck();
                return $check($actualArgs !== null ? $actualArgs : [$firstArg]);
            })
        ];
    }

    /**
     * On PHPUnit 9, the arguments of the call whose parameters PHPUnit is checking, or null if not found
     *
     * @return array|null
     */
    private static function argsOfInvocationUnderCheck()
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof ParametersRule) {
                $invocation = TestHelper::makeAccessible(new ReflectionProperty($frame['object'], 'invocation'));
                return $invocation->getValue($frame['object'])->getParameters();
            }
        }
        return null;
    }

    /**
     * @param mixed $expected value or constraint
     * @param mixed $actual
     * @param int   $position
     * @param int   $call
     */
    private static function assertConsecutiveArg($expected, $actual, $position, $call)
    {
        $constraint = $expected instanceof Constraint ? $expected : static::equalTo($expected);
        $constraint->evaluate($actual, sprintf('Parameter %d of call %d does not match.', $position, $call));
    }
}

if (!class_exists('\PHPUnit\Framework\Constraint\ArraySubset')) {
    // phpcs:ignore
    class ArraySubset extends \PHPUnit\Framework\Constraint\Constraint
    {
        /**
         * @var array|\Traversable
         */
        protected $subset;

        /**
         * @var bool
         */
        protected $strict;

        /**
         * @param array|\Traversable $subset
         * @param bool               $strict Check for object identity
         */
        public function __construct($subset, $strict = false)
        {
            $this->strict = $strict;
            $this->subset = $subset;
        }

        /**
         * Returns a string representation of the constraint.
         *
         * @return string
         */
        public function toString(): string
        {
            return 'has the subset ' . $this->exporter->export($this->subset);
        }

        /**
         * Evaluates the constraint for parameter $other. Returns true if the
         * constraint is met, false otherwise.
         *
         * @param array|\Traversable $other Array or Traversable object to evaluate.
         *
         * @return bool
         */
        protected function matches($other): bool
        {
            //type cast $other & $this->subset as an array to allow
            //support in standard array functions.
            $other = $this->toArray($other);
            $this->subset = $this->toArray($this->subset);

            $patched = \array_replace_recursive($other, $this->subset);

            if ($this->strict) {
                return $other === $patched;
            }

            return $other == $patched;
        }

        /**
         * Returns the description of the failure
         *
         * The beginning of failure messages is "Failed asserting that" in most
         * cases. This method should return the second part of that sentence.
         *
         * @param mixed $other Evaluated value or object.
         *
         * @return string
         */
        protected function failureDescription($other): string
        {
            return 'an array ' . $this->toString();
        }

        /**
         * @param array|\Traversable $other
         *
         * @return array
         */
        private function toArray($other)
        {
            if (\is_array($other)) {
                return $other;
            }
            // phpcs:ignore
            if ($other instanceof \ArrayObject) {
                return $other->getArrayCopy();
            }

            if ($other instanceof \Traversable) {
                return \iterator_to_array($other);
            }

            // Keep BC even if we know that array would not be the expected one
            return (array) $other;
        }
    }
}
