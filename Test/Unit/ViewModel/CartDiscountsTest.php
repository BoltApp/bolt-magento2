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

namespace Bolt\Boltpay\Test\Unit\ViewModel;

use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\ViewModel\CartDiscounts;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * @coversDefaultClass \Bolt\Boltpay\ViewModel\CartDiscounts
 */
class CartDiscountsTest extends BoltTestCase
{
    const DEFAULT_LAYOUT = [
        [
            'parent' => 'minicart_content.extra_info',
            'name' => 'minicart_content.extra_info.rewards',
            'component' => 'Magento_Reward/js/view/payment/reward',
            'config' => [],
        ],
        [
            'parent' => 'minicart_content.extra_info',
            'name' => 'minicart_content.extra_info.rewards_total',
            'component' => 'Magento_Reward/js/view/cart/reward',
            'config' => [
                'template' => 'Magento_Reward/cart/reward',
                'title' => 'Reward Points',
            ],
        ]
    ];

    /**
     * @var CartDiscounts
     */
    private $cartDiscounts;

    private $objectManager;

    private $eventsForThirdPartyModules;

    /**
     * @var SerializerInterface
     */
    private $serializerInterface;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cartDiscounts = $this->objectManager->create(CartDiscounts::class);
        $this->serializerInterface = $this->objectManager->create(SerializerInterface::class);
    }

    /**
     * @covers ::getJsLayout
     *
     * @throws \ReflectionException
     */
    public function getJsLayout_returnFalse()
    {
        static::assertFalse(TestHelper::invokeMethod($this->cartDiscounts, 'getJsLayout'));
    }

    /**
     * @test
     * @covers ::getJsLayout
     * @throws \ReflectionException
     */
    public function getJsLayout_returnObject()
    {
        $this->eventsForThirdPartyModules = $this->createMock(EventsForThirdPartyModules::class, ['runFilter']);
        $this->eventsForThirdPartyModules->method('runFilter')->willReturn(self::DEFAULT_LAYOUT);
        TestHelper::setInaccessibleProperty($this->cartDiscounts, 'eventsForThirdPartyModules', $this->eventsForThirdPartyModules);
        static::assertEquals(
            $this->serializerInterface->serialize(
                [
                    'components' => [
                        'bolt-checkout-cart-discounts' => [
                            'component' => 'uiComponent',
                            'children' => self::DEFAULT_LAYOUT
                        ]
                    ]
                ]
            ),
            $this->cartDiscounts->getJsLayout()
        );
    }

    /**
     * @test
     * @covers ::getId
     */
    public function getId()
    {
        self::assertEquals('bolt-checkout-cart-discounts', $this->cartDiscounts->getId());
    }
}
