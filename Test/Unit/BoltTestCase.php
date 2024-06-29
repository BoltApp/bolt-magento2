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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit;

use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version as PHPUnitVersion;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;

if (PHPUnitVersion::id() < 9) {
    // phpcs:ignore
    class BoltTestCase extends TestCase
    {
        protected function setUp()
        {
            $this->setUpInternal();
        }

        protected function tearDown()
        {
            parent::tearDown();
            $this->tearDownInternal();
        }

        public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = '')
        {
            static::assertRegExp($pattern, $string, $message);
        }

        /**
         * @throws \PHPUnit\Framework\ExpectationFailedException
         * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
         */
        public static function assertStringContainsString(string $needle, string $haystack, string $message = '')
        {
            $constraint = new StringContains($needle, false);
            static::assertThat($haystack, $constraint, $message);
        }

        protected function setUpInternal()
        {
        }

        protected function tearDownInternal()
        {
        }
    }
} else {
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

                $attribute->setAccessible(true);
                $value = $attribute->getValue($object);
                $attribute->setAccessible(false);

                return $value;
            }

            throw new Exception(
                \sprintf(
                    'Attribute "%s" not found in object.',
                    $attributeName
                )
            );
        }

        public static function assertInternalType($expected, $actual, $message = '')
        {
            if (!\is_string($expected)) {
                throw InvalidArgumentHelper::factory(1, 'string');
            }

            $constraint = new IsType(
                $expected
            );

            static::assertThat($actual, $constraint, $message);
        }

        /**
         * @param string $messageRegExp
         *
         * @throws Exception
         */
        public function expectExceptionMessageRegExp($messageRegExp)
        {
            if (!\is_string($messageRegExp)) {
                throw InvalidArgumentHelper::factory(1, 'string');
            }

            $this->expectedExceptionMessageRegExp = $messageRegExp;
        }

        protected function setUpInternal()
        {
        }

        protected function tearDownInternal()
        {
        }

        protected function createPartialMock($originalClassName, array $methods): \PHPUnit\Framework\MockObject\MockObject
        {
            return $this->getMockBuilder($originalClassName)
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning()
                ->disallowMockingUnknownTypes()
                ->setMethods(empty($methods) ? null : $methods)
                ->getMock();
        }
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
