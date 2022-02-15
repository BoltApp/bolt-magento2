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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\DefaultOrderStatusInterface;

/**
 * Class DefaultOrderStatus.
 * 
 * @package Bolt\Boltpay\Model\Api\Data
 */
class DefaultOrderStatus implements DefaultOrderStatusInterface, \JsonSerializable
{
    /**
     * @var string
     */
    private $state;

    /**
     * @var string
     */
    private $status;

    /**
     * Get state.
     *
     * @return string
     * @api
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set state.
     *
     * @param string $state
     *
     * @return $this
     * @api
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     * @api
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return $this
     * @api
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'state' => $this->state,
            'status' => $this->status,
        ];
    }
}
