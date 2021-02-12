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

namespace Bolt\Boltpay\ViewModel;

use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\Framework\Serialize\SerializerInterface;

class CartDiscounts implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(EventsForThirdPartyModules $eventsForThirdPartyModules, SerializerInterface $serializer)
    {
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->serializer = $serializer;
    }

    public function getJsLayout()
    {
        $discounts = $this->eventsForThirdPartyModules->runFilter('collectCartDiscountJsLayout', []);
        if (empty($discounts)) {
            return false;
        }
        return $this->serializer->serialize(
            [
                'components' => [
                    $this->getId() => [
                        'component' => 'uiComponent',
                        'children'  => $discounts
                    ]
                ]
            ]
        );
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'bolt-checkout-cart-discounts';
    }
}