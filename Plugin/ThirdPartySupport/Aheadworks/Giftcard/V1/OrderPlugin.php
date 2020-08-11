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

namespace Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1;

use Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin;
use Bolt\Boltpay\Helper\Order;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin;
use Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext;
use Magento\Sales\Model\Service\OrderService;

/**
 * Plugin class for {@see \Bolt\Boltpay\Helper\Order}
 * Used to add support for Aheadworks Giftcard
 */
class OrderPlugin extends AbstractPlugin
{
    /**
     * @var ThirdPartyModuleFactory that returns {@see \Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin}
     */
    private $aheadworksGiftcardOrderServicePluginFactory;

    /**
     * @var OrderService Magento order management model
     */
    private $orderService;

    /**
     * Order plugin constructor
     *
     * @param CommonModuleContext     $context used to provide common dependencies and preconditions
     * @param ThirdPartyModuleFactory $aheadworksGiftcardOrderServicePluginFactory Aheadworks Giftcard order service plugin
     * @param OrderService            $orderService Magento order service instance
     */
    public function __construct(
        CommonModuleContext $context,
        ThirdPartyModuleFactory $aheadworksGiftcardOrderServicePluginFactory,
        OrderService $orderService
    ) {
        parent::__construct($context);
        $this->aheadworksGiftcardOrderServicePluginFactory = $aheadworksGiftcardOrderServicePluginFactory;
        $this->orderService = $orderService;
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     * Used to restore Aheadworks Giftcard balance for failed payment orders by manually executing the appropriate
     * plugin {@see \Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin::aroundCancel}
     * because it is plugged into {@see \Magento\Sales\Api\OrderManagementInterface::cancel} instead of
     * {@see \Magento\Sales\Model\Order::cancel} which we call in {@see \Bolt\Boltpay\Helper\Order::deleteOrder}
     *
     * @param Order                      $subject Bolt Order helper
     * @param \Magento\Sales\Model\Order $order to be deleted
     */
    public function beforeDeleteOrder(Order $subject, $order)
    {
        if ($this->shouldRun() && $this->aheadworksGiftcardOrderServicePluginFactory->isAvailable()
            && $this->aheadworksGiftcardOrderServicePluginFactory->isExists()
        ) {
            /** @var OrderServicePlugin $aheadworksGiftcardOrderServicePlugin */
            $aheadworksGiftcardOrderServicePlugin = $this->aheadworksGiftcardOrderServicePluginFactory->getInstance();
            $aheadworksGiftcardOrderServicePlugin->aroundCancel(
                $this->orderService,
                function ($orderId) {
                    return true;
                },
                $order->getId()
            );
        }
    }
}
