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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Authorization;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Authorization\HookContext;
use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Authorization\Model\UserContextInterface;

class HookContextTest extends BoltTestCase
{
    /**
     * @var HookContext
     */
    private $hookContext;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->hookContext = $this->objectManager->create(HookContext::class);
    }

    /**
     * @test
     */
    public function getUserId()
    {
        $this->assertEquals(0, $this->hookContext->getUserId());
    }

    /**
     * @test
     */
    public function getUserType()
    {
        $this->assertEquals(UserContextInterface::USER_TYPE_INTEGRATION, $this->hookContext->getUserType());
    }
}
