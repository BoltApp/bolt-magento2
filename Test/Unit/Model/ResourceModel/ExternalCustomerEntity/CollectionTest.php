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

namespace Bolt\Boltpay\Test\Unit\Model\ResourceModel\ExternalCustomerEntity;

use Bolt\Boltpay\Model\ResourceModel\ExternalCustomerEntity\Collection as ExternalCustomerEntityCollection;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Bolt\Boltpay\Model\ExternalCustomerEntityFactory;

class CollectionTest extends BoltTestCase
{
    const EXTERNAL_ID = 1;
    const CUSTOMER_ID = 1;

    /**
     * @var ExternalCustomerEntityCollection
     */
    private $externalCustomerEntityCollection;

    /**
     * @var ExternalCustomerEntityFactory
     */
    private $externalCustomerEntityFactory;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Setup for CollectionTest Class
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->externalCustomerEntityCollection = $this->objectManager->create(ExternalCustomerEntityCollection::class);
        $this->externalCustomerEntityFactory = $this->objectManager->create(ExternalCustomerEntityFactory::class);
    }

    /**
     * @test
     */
    public function getExternalCustomerEntityByExternalID_returnsCorrectObject_ifCollectionIsNotEmpty()
    {
        $externalCustomerEntityFactory = $this->externalCustomerEntityFactory->create()
            ->setData('external_id', self::EXTERNAL_ID)
            ->setData('customer_id', self::CUSTOMER_ID)
            ->save();
        $externalCustomerEntityFactoryId = $externalCustomerEntityFactory->getId();
        $this->assertEquals(
            $externalCustomerEntityFactoryId,
            $this->externalCustomerEntityCollection
                ->getExternalCustomerEntityByExternalID(self::EXTERNAL_ID)
                ->getId()
        );
    }

    /**
     * @test
     */
    public function getExternalCustomerEntityByExternalID_returnsNull_ifNoItemsFound()
    {
        $this->assertNull($this->externalCustomerEntityCollection->getExternalCustomerEntityByExternalID('test_external_id'));
    }
}
