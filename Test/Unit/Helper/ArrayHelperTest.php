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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
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
    public function getValueFromArray($data)
    {
        $this->assertEquals($data['expect'], ArrayHelper::getValueFromArray($data['array'], $data['key'], $data['default']));
    }

    public function arrayCases()
    {
        return [
            [
                'data' => [
                    'array' => [
                        'key1' => 'qwerty'
                    ],
                    'key' => 'key1',
                    'default' => '',
                    'expect' => 'qwerty'
                ]
            ],
            [
                'data' => [
                    'array' => [
                        'key1' => 'qwerty'
                    ],
                    'key' => 'asd',
                    'default' => '',
                    'expect' => ''
                ]
            ],
            [
                'data' => [
                    'array' => [
                        'key1' => 'qwerty'
                    ],
                    'key' => 'asd',
                    'default' => '123',
                    'expect' => '123'
                ]
            ],
            [
                'data' => [
                    'array' => [
                        'key1' => [
                            'key2' => 'asdfdfsgklnsxdkl'
                        ]
                    ],
                    'key' => 'key1.key2',
                    'default' => null,
                    'expect' => 'asdfdfsgklnsxdkl'
                ]
            ],
            [
                'data' => [
                    'array' => [
                        'key1' => [
                            'key2' => 'asdfdfsgklnsxdkl'
                        ]
                    ],
                    'key' => 'key1',
                    'default' => null,
                    'expect' => [
                        'key2' => 'asdfdfsgklnsxdkl'
                    ]
                ]
            ],

        ];
    }

    /**
     * @test
     * @param $data
     * @dataProvider displayIdCases
     * @group ArrayHelper
     */
    public function extractDataFromDisplayId($data)
    {
        $this->assertEquals($data['expect'], ArrayHelper::extractDataFromDisplayId($data['display_id']));
    }

    public function displayIdCases()
    {
        return [
            ['data' => ['display_id' => '', 'expect' => []]],
            ['data' => ['display_id' => null, 'expect' => []]],
            ['data' => ['display_id' => '1001001 / 123', 'expect' => ['1001001', '123']]],
            ['data' => ['display_id' => '1001001 / ', 'expect' => ['1001001', '']]],
            ['data' => ['display_id' => '1001001 / ', 'expect' => ['1001001', null]]],
            ['data' => ['display_id' => ' / 123', 'expect' => ['', '123']]],
            ['data' => ['display_id' => ' / 123', 'expect' => [null, '123']]],
            ['data' => ['display_id' => 123, 'expect' => ['123', '']]],
            ['data' => ['display_id' => 123, 'expect' => ['123', null]]],
            ['data' => ['display_id' => '1001001 / 123 / 4444', 'expect' => ['1001001', '123']]],
        ];
    }
}
