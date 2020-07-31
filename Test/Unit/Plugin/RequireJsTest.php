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

namespace Bolt\Boltpay\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\RequireJs\Config\File\Collector\Aggregated as RequireJsCollector;
use Bolt\Boltpay\Plugin\RequireJs;
use Magento\Framework\View\File;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\RequireJs
 */
class RequireJsTest extends TestCase
{

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var RequireJsCollector
     */
    private $subject;

    /**
     * @var File
     */
    private $file;

    /**
     * @var RequireJs
     */
    private $plugin;

    public function setUp()
    {
        $this->productMetadata = $this->getMockBuilder(ProductMetadataInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getVersion', 'getName', 'getEdition'])
            ->getMock();

        $this->subject = $this->createPartialMock(RequireJsCollector::class, ['getVersion']);
        $this->file = $this->createPartialMock(File::class, ['getModule']);
        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            RequireJs::class,
            [
                'productMetadata' => $this->productMetadata
            ]
        );
    }

    /**
     * @test
     * @covers ::afterGetFiles
     */
    public function afterGetFiles_willReturnEmptyArray()
    {
        $this->productMetadata->expects(self::once())->method('getVersion')->willReturn('2.3.0');
        $this->file->expects(self::any())->method('getModule')->willReturn('Bolt_Boltpay');
        $result = $this->plugin->afterGetFiles($this->subject, [$this->file]);
        $this->assertEquals([], $result);
    }

    /**
     * @test
     * @covers ::afterGetFiles
     * @param $version
     * @param $moduleName
     * @param $expectedCall
     * @dataProvider dataProvider_afterGetFiles_willReturnAnArray
     */
    public function afterGetFiles_willReturnAnArray($version, $expectedCall, $moduleName)
    {
        $this->productMetadata->expects(self::once())->method('getVersion')->willReturn($version);
        $this->file->expects($expectedCall)->method('getModule')->willReturn($moduleName);
        $result = $this->plugin->afterGetFiles($this->subject, [$this->file]);
        $this->assertEquals([$this->file], $result);
    }

    public function dataProvider_afterGetFiles_willReturnAnArray()
    {
        return [
            ['2.1.0', self::never(), 'Bolt_Boltpay'],
            ['2.3.0', self::once(), 'Is_Not_Bolt_Boltpay'],
        ];
    }
}
