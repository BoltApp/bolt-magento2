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

namespace Bolt\Boltpay\Api\Data;

/**
 * UniversalApi result interface. Wrapping response object for universal api
 * 
 * @api
 */
interface UniversalApiResultInterface
{
    /**
     * Set event string
     * 
     * @api
     * @param string $event
     * @return $this
     */
    public function setEvent($event);

    /**
     * Get event string
     * 
     * @api
     * @return string
     */
    public function getEvent();

    /**
     * Set status string success|failure
     * 
     * @api
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get status string
     * 
     * @api
     * @return string
     */
    public function getStatus();

    /**
     * Set response data
     * 
     * @api
     * @param mixed $data
     * @return $this
     */
    public function setData($data);

    /**
     * Get response data
     * 
     * @api
     * @return mixed
     */
    public function getData();
}