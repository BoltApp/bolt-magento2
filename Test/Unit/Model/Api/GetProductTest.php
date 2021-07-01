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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class GetProductTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\GetProduct
 */
class GetProductTest extends BoltTestCase
{

    /**
     * @test
     * that getConfigurableProductOptions returns configurable attributes and their options for configurable product
     *
     * @covers ::getConfigurableProductOptions
     */
    public function getConfigurableProductOptions_forConfigurableProduct_returnsConfigurableAttributes()
    {
        $om = Bootstrap::getObjectManager();
        if (version_compare(
            $om->get(\Magento\Framework\App\ProductMetadataInterface::class)->getVersion(),
            '2.3',
            '<'
        )) {
            $this->markTestSkipped('Version no longer supported');
        }
        
        $configurableProductId = \Bolt\Boltpay\Test\Unit\TestUtils::getConfigurableProduct()->getId();
        /** @var \Bolt\Boltpay\Model\Api\GetProduct $getProduct */
        $getProduct = $om->get(\Bolt\Boltpay\Model\Api\GetProduct::class);

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
        $productRepository = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

        $result = TestHelper::invokeMethod(
            $getProduct,
            'getConfigurableProductOptions',
            [$productRepository->getById($configurableProductId)]
        );
        static::assertCount(1, $result);

        $color = array_shift($result);

        static::assertEquals('color', $color['code']);
        static::assertEquals('Color', $color['label']);
        static::assertCount(1, $color['options']);
    }
}
