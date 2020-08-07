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

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Metric;

class MetricTest extends TestCase
{
    const KEY = 'key';
    const DATA = ['value' => '2222'];

    /**
     * @var \Bolt\Boltpay\Helper\Metric
     */
    protected $metric;

    protected function setUp()
    {
        $this->metric = new Metric(self::KEY, self::DATA);
    }

    /**
     * @test
     */
    public function getMetricJson()
    {
        $this->assertEquals('{"key":{"value":"2222"}}', $this->metric->getMetricJson());
    }

    /**
     * @test
     */
    public function jsonSerialize()
    {
        $this->assertEquals(['key' => ['value' => '2222']], $this->metric->jsonSerialize());
    }
}
