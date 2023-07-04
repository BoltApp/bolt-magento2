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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Model\Api\Data\DefaultOrderStatusFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;

/**
 * Get Default Order Statuses
 *
 * @api
 */
class GetDefaultOrderStatuses implements \Bolt\Boltpay\Api\GetDefaultOrderStatusesInterface
{
    /**
     * @var OrderConfig
     */
    private $_orderConfig;

    /**
     * @var DefaultOrderStatusFactory
     */
    private $defaultOrderStatusFactory;

    /**
     * @param OrderConfig               $orderConfig
     * @param DefaultOrderStatusFactory $DefaultOrderStatusFactory
     */
    public function __construct(
        OrderConfig               $orderConfig,
        DefaultOrderStatusFactory $defaultOrderStatusFactory
    ) {
        $this->_orderConfig = $orderConfig;
        $this->defaultOrderStatusFactory = $defaultOrderStatusFactory;
    }

    /**
     * Get default order statuses
     *
     * @api
     *
     * @return \Bolt\Boltpay\Api\Data\DefaultOrderStatusInterface[]
     */
    public function get() {
        $result = [];
        $states = [
            Order::STATE_NEW,
            Order::STATE_PENDING_PAYMENT,
            Order::STATE_PROCESSING,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_CANCELED,
            Order::STATE_HOLDED,
            Order::STATE_PAYMENT_REVIEW,
        ];
        foreach ($states as $state) {
            $status = $this->_orderConfig->getStateDefaultStatus($state);
            $result[] = $this->defaultOrderStatusFactory->create()
                ->setState($state)->setStatus($status);
        }
        return $result;
    }
}
