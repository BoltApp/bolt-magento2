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
namespace Bolt\Boltpay\Api\Data;

/**
 * Default Order status interface.
 *
 * @api
 */
interface DefaultOrderStatusInterface
{

    /**
     * Get state.
     *
     * @return string
     * @api
     */
    public function getState();

    /**
     * Set state.
     *
     * @param string $state
     *
     * @return $this
     * @api
     */
    public function setState($state);

    /**
     * Get status
     *
     * @return string
     * @api
     */
    public function getStatus();

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return $this
     * @api
     */
    public function setStatus($status);
 }