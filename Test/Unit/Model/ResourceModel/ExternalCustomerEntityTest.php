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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel;

use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;

class ExternalCustomerEntityTest extends BoltTestCase
{
    /**
     * @var ExternalCustomerEntity
     */
    private $externalCustomerEntityMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->externalCustomerEntityMock = $this->getMockBuilder(ExternalCustomerEntity::class)
            ->disableOriginalConstructor()
            ->setMethods(['_init'])
            ->getMock();
    }

    /**
     * @test
     */
    public function testConstruct()
    {
        $this->externalCustomerEntityMock->expects($this->once())->method('_init')
            ->with('bolt_external_customer_entity', 'id')
            ->willReturnSelf();
        TestHelper::invokeMethod($this->externalCustomerEntityMock, '_construct');
    }
}
