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

use \PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\ArrayHelper;

/**
 * Class ArrayHelperTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class ArrayHelperTest extends TestCase
{
    /**
     * @test
     * @dataProvider arrayCases
     * @group ArrayHelper
     */
    public function getValueFromArray($array, $key, $default, $expect)
    {
        $this->assertEquals($expect, ArrayHelper::getValueFromArray($array, $key, $default));
    }

    public function arrayCases()
    {
        return [
            [
                'array' => [
                    'key1' => 'value1'
                ],
                'key' => 'key1',
                'default' => '',
                'expect' => 'value1'
            ],
            [
                'array' => [
                    'key1' => 'value1'
                ],
                'key' => 'key2',
                'default' => '',
                'expect' => ''
            ],
            [
                'array' => [
                    'key1' => 'value1'
                ],
                'key' => 'key2',
                'default' => 'value2',
                'expect' => 'value2'
            ],
            [
                'array' => [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => 'key1.key2',
                'default' => null,
                'expect' => 'value2'
            ],
            [
                'array' => [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => 'key1',
                'default' => null,
                'expect' => [
                    'key2' => 'value2'
                ]
            ],
            [
                'array' => [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => ['key1'],
                'default' => null,
                'expect' => [
                    'key2' => 'value2'
                ]
            ],
            [
                'array' => [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => ['key1', 'key2'],
                'default' => null,
                'expect' => 'value2'
            ],
            [
                'array' => (object) [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => ['key1', 'key2'],
                'default' => null,
                'expect' => 'value2'
            ],
            [
                'array' => [
                    'key1' => [
                        'key2' => 'value2'
                    ]
                ],
                'key' => function () {
                    return 'value3';
                },
                'default' => null,
                'expect' => 'value3'
            ],
            [
                'array' => 'no array',
                'key' => ['key1'],
                'default' => null,
                'expect' => null
            ]
        ];
    }

    /**
     * Determine if two associative arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    private function checkArraysAreSimilar($a, $b)
    {
        // if the indexes don't match, return immediately
        if (count(array_diff_assoc($a, $b))) {
            return false;
        }
        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach ($a as $k => $v) {
            if ($v !== $b[$k]) {
                return false;
            }
        }
        // we have identical indexes, and no unequal values
        return true;
    }

    /**
     * @test
     * @param $data
     * @dataProvider displayIdCases
     * @group ArrayHelper
     */
    public function extractDataFromDisplayId($display_id, $expect)
    {
        $this->assertTrue($this->checkArraysAreSimilar($expect, ArrayHelper::extractDataFromDisplayId($display_id)));
    }

    public function displayIdCases()
    {
        return [
            ['display_id' => '', 'expect' => []],
            ['display_id' => null, 'expect' => []],
            ['display_id' => '1001001 / 123', 'expect' => ['1001001', '123']],
            ['display_id' => '1001001 / ', 'expect' => ['1001001', '']],
            ['display_id' => ' / 123', 'expect' => ['', '123']],
            ['display_id' => 123, 'expect' => ['123', null]],
            ['display_id' => '1001001 / 123 / 4444', 'expect' => ['1001001', '123']],
        ];
    }
}
