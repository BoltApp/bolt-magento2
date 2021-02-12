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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\ArrayHelper;

/**
 * Class ArrayHelperTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper
 */
class ArrayHelperTest extends BoltTestCase
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
}
