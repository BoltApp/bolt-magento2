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

interface UniversalWebhookResultInterface
{
    /**
     * Get status string
     * 
     * @api
     * @return string
     */

    public function getStatus();

    /**
     * Set status string
     * 
     * @api
     * @param string $status
     * @return $this
     */

    public function setStatus($status);

    /**
     * Get error object
     * 
     * @api
     * @return array
     */

    public function getError();

    /**
     * Set error object
     * 
     * @api
     * @param array $error
     * @return $this
     */

    public function setError($error);
}