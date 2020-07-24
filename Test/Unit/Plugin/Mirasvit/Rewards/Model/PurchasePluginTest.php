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

namespace Bolt\Boltpay\Test\Unit\Plugin\Mirasvit\Rewards\Model;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\Mirasvit\Rewards\Model\PurchasePlugin;
use Magento\Framework\UrlInterface;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Mirasvit\Rewards\Model\PurchasePlugin
 */
class PurchasePluginTest extends TestCase
{
    /**
     * @var PurchasePlugin
     */
    protected $plugin;

    /**
     * @var \Mirasvit\Rewards\Model\Purchase
     */
    protected $subject;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var callable
     */
    private $proceed;

    /** @var callable */
    private $callback;

    public function setUp()
    {
        $this->urlInterface = $this->getMockBuilder(UrlInterface::class)
            ->getMock();

        $this->bugsnag = $this->createMock(Bugsnag::class);

        $this->subject = $this->getMockBuilder('\Mirasvit\Rewards\Model\Purchase')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var callable $callback */
        $this->callback = $callback = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])->getMock();
        $this->proceed = function ($order) use ($callback) {
            return $callback($order);
        };

        $this->plugin = (new ObjectManager($this))->getObject(
            PurchasePlugin::class,
            [
                'bugsnag' => $this->bugsnag,
                'urlInterface' => $this->urlInterface
            ]
        );
    }

    /**
     * @test
     * @dataProvider dataProvider_aroundRefreshPointsNumber
     * @covers ::aroundRefreshPointsNumber
     *
     * @param $url
     * @param $expectedCall
     * @throws \Exception
     */
    public function aroundRefreshPointsNumber($url, $expectedCall)
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn($url);
        $this->callback->expects(self::exactly($expectedCall))->method('__invoke')->with(true)->willReturnSelf();
        $this->plugin->aroundRefreshPointsNumber($this->subject, $this->proceed, true);
    }

    public function dataProvider_aroundRefreshPointsNumber()
    {
        return [
            [null, 1],
            ['/checkout/cart/index', 1],
            ['/rewards/checkout/updatePaymentMethodPost', 0],
        ];
    }
}
