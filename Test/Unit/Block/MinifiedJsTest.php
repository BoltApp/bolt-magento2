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

namespace Bolt\Boltpay\Test\Unit\Block;

use Bolt\Boltpay\Block\MinifiedJs;
use Bolt\Boltpay\Test\Unit\TestHelper;

/**
 * Class MinifiedJsTest
 * @coversDefaultClass \Bolt\Boltpay\Block\MinifiedJs
 * @package Bolt\Boltpay\Test\Unit\Block
 */
class MinifiedJsTest extends \PHPUnit\Framework\TestCase
{
    const MINIFY_HTML = '<script>console.log("Bolt")</script>';

    /**
     * @var MinifiedJs
     */
    private $block;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->block = $this->createPartialMock(MinifiedJs::class, ['minifyJs']);
    }

    /**
     * @test
     *
     * @covers ::toHtml
     */
    public function toHtml()
    {
        $this->block->expects(self::once())->method('minifyJs')->withAnyParameters()->willReturn(self::MINIFY_HTML);
        $this->assertEquals(self::MINIFY_HTML, TestHelper::invokeMethod($this->block, '_toHtml'));
    }
}
