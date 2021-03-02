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

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\CreatedOrderInterface;

class CreatedOrder implements CreatedOrderInterface, \JsonSerializable
{
    /**
     * @var int
     */
    private $displayId;

    /**
     * @var string
     */
    private $orderReceivedUrl;

    /**
     * @api
     * @param int $displayId
     * @return $this
     */
    public function setDisplayId($displayId)
    {
        $this->displayId = $displayId;
        return $this;
    }

    /**
     * @api
     * @return int
     */
    public function getDisplayId()
    {
        return $this->displayId;
    }

    /**
     * @api
     * @param string $orderReceivedUrl
     * @return $this
     */
    public function setOrderReceivedUrl($orderReceivedUrl)
    {
        $this->orderReceivedUrl = $orderReceivedUrl;
        return $this;
    }

    /**
     * @api
     * @return string
     */
    public function getOrderReceivedUrl()
    {
        return $this->orderReceivedUrl;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'display_id' => $this->displayId,
            'order_received_url' => $this->orderReceivedUrl
        ];
    }
}