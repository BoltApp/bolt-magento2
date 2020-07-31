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

use Bolt\Boltpay\Helper\SecretObscurer;
use PHPUnit\Framework\TestCase;

class SecretObscurerTest extends TestCase
{
    /**
     * @test
     */
    public function obscure_empty()
    {
        $this->assertEquals('', SecretObscurer::obscure(''));
    }

    /**
     * @test
     */
    public function obscure_short_string()
    {
        $this->assertEquals('***', SecretObscurer::obscure('abcde'));
    }

    /**
     * @test
     */
    public function obscure_long_string()
    {
        $this->assertEquals('aaa***bbb', SecretObscurer::obscure('aaaabbbb'));
    }
}
