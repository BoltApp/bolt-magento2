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

namespace Bolt\Boltpay\Test\Unit\Model\Api\Data;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\AccountInfo;

/**
 * Class AccountInfoTest
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class AccountInfoTest extends TestCase
{
    /**
     * @var \Bolt\Boltpay\Model\Api\Data\AccountInfo
     */
    protected $accountInfo;

    protected function setUp()
    {
        $this->accountInfo = new AccountInfo();
    }

    /**
     * @test
     */
    public function setAndGetStatus()
    {
        $this->accountInfo->setStatus(true);
        $this->assertTrue($this->accountInfo->getStatus());
    }

    /**
     * @test
     */
    public function setAndGetAccountExists()
    {
        $this->accountInfo->setAccountExists(true);
        $this->assertTrue($this->accountInfo->getAccountExists());
    }
}
