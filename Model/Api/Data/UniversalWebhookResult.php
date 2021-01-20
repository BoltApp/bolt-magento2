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

use Bolt\Boltpay\Api\Data\UniversalWebhookResultInterface;

class UniversalWebhookResult implements UniversalWebhookResultInterface
{
    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $error;

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
     * Set status string
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
     * Get error string
     * 
     * @api
     * @return string
     */

    public function getError()
    {
        return $this->error;
    }

    /**
     * Set error string
     * 
     * @api
     * @param string $error  
     * @return $this
     */
    public function setError($error)
    {
        $this->error = $error;
        return $this;
    }

}