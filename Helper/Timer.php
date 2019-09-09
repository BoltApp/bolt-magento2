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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace Bolt\Boltpay\Helper;

/**
 * Timer class used to measure latency of requests in ms
 */
class Timer
{
    /**
     * @var int
     */
    protected $startTime;

    function __construct() {
        $this->startTime = round(microtime(true) * 1000);
    }

    /**
     * Restarts the timer to the current time
     *
     * @return void
     */
    public function startTimer() {
        $this->startTime = round(microtime(true) * 1000);
    }

    /**
     * Returns the amount of ms since the start time
     *
     * @return int
     */
    public function getElapsedTime() {
        return round(microtime(true) * 1000) - $this->startTime;
    }
}