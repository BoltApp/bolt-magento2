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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Exception;

use Bolt\Boltpay\Exception\BoltException;

/**
 * BoltExceptionTest
 *
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class BoltExceptionTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var BoltException
     */
    protected $boltException;

    /**
     * @dataProvider provider_construct
     * @test
     *
     * @param $phrase
     * @param $code
     */
    public function construct($phrase, $code)
    {
        $exception = new BoltException(
            __($phrase),
            null,
            $code
        );

        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($phrase, $exception->getMessage());
    }

    public function provider_construct()
    {
        return [
            ['test', null],
            ['test1', 1],
        ];
    }
}
