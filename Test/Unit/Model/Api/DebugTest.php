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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Model\Api\Data\DebugInfo;
use Bolt\Boltpay\Model\Api\Debug;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\Debug
 */
class DebugTest extends TestCase
{
	/**
	 * @var Debug
	 */
	private $debug;

	/**
	 * @var DebugInfoFactory
	 */
	private $debugInfoFactory;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadataInterfaceMock;

	/**
	 * @inheritdoc
	 */
	public function setUp()
	{
		$this->productMetadataInterfaceMock = $this->createMock(ProductMetadataInterface::class);
		$this->productMetadataInterfaceMock->method('getVersion')->willReturn('2.3.0');

		$this->debugInfoFactory = $this->createMock(DebugInfoFactory::class);
		$this->debugInfoFactory->method('create')->willReturn(new DebugInfo());

		$objectManager = new ObjectManager($this);
		$this->debug = $objectManager->getObject(
			Debug::class,
			[
				'debugInfoFactory' => $this->debugInfoFactory,
				'productMetadata' => $this->productMetadataInterfaceMock
			]
		);
	}

	/**
	 * @test
	 * @covers ::debug
	 */
	public function debug_successful()
	{
		$debugInfo = $this->debug->debug();
		$this->assertNotNull($debugInfo);
		$this->assertNotNull($debugInfo->getPhpVersion());
		$this->assertEquals('2.3.0', $debugInfo->getPlatformVersion());
	}
}