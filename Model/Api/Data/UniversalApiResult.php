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

namespace Bolt\Boltpay\Model\Api\Data;

use Bolt\Boltpay\Api\Data\UniversalApiResultInterface;

class UniversalApiResult implements UniversalApiResultInterface
{
    /**
     * @var string
     */
    private $event;

    /**
     * @var string
     */
    private $status;

    /**
     * @var mixed
     */
    private $data;

    /**
     * Set event string
     * 
     * @api
     * @param string $event
     * @return $this
     */
    public function setEvent($event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Get event string
     * 
     * @api
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Set status string success|failure
     * 
     * @api
     * @param string $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /** 
     * Get status string
     * 
     * @api
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set response data
     * 
     * @api
     * @param mixed $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Get response data
     * 
     * @api
     * @return mixed $data
     */
    public function getData()
    {
        return $this->data;
    }
}