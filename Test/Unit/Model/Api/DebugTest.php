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

use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\Api\Data\DebugInfo;
use Bolt\Boltpay\Model\Api\Data\DebugInfoFactory;
use Bolt\Boltpay\Model\Api\Debug;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

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
	private $debugInfoFactoryMock;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManagerInterfaceMock;

	/**
	 * @var HookHelper
	 */
	private $hookHelperMock;

	/**
	 * @var ProductMetadataInterface
	 */
	private $productMetadataInterfaceMock;

	/**
	 * @inheritdoc
	 */
	public function setUp()
	{
		// prepare debug info factory
		$this->debugInfoFactoryMock = $this->createMock(DebugInfoFactory::class);
		$this->debugInfoFactoryMock->method('create')->willReturn(new DebugInfo());

		// prepare store manager
		$storeInterfaceMock = $this->createMock(StoreInterface::class);
		$storeInterfaceMock->method('getId')->willReturn(0);
		$this->storeManagerInterfaceMock = $this->createMock(StoreManagerInterface::class);
		$this->storeManagerInterfaceMock->method('getStore')->willReturn($storeInterfaceMock);

		// prepare product meta data
		$this->productMetadataInterfaceMock = $this->createMock(ProductMetadataInterface::class);
		$this->productMetadataInterfaceMock->method('getVersion')->willReturn('2.3.0');

		// prepare hook helper
		$this->hookHelperMock = $this->createMock(HookHelper::class);
		$this->hookHelperMock->method('preProcessWebhook');


		// initialize test object
		$objectManager = new ObjectManager($this);
		$this->debug = $objectManager->getObject(
			Debug::class,
			[
				'debugInfoFactory' => $this->debugInfoFactoryMock,
				'storeManager' => $this->storeManagerInterfaceMock,
				'hookHelper' => $this->hookHelperMock,
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
		$this->hookHelperMock->expects($this->once())->method('preProcessWebhook');

		$debugInfo = $this->debug->debug();
		$this->assertNotNull($debugInfo);
		$this->assertNotNull($debugInfo->getPhpVersion());
		$this->assertEquals('2.3.0', $debugInfo->getPlatformVersion());
	}
}