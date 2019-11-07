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
 * Boltpay Metrics used by the Metric Client
 */
class Metric implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $key;
    /**
     * @var array
     */
    protected $data;

    /**
     * @param string $key
     * @param array $data
     *
     */
    function __construct($key,$data) {
        $this->key = $key;
        $this->data = $data;
    }


    /**
     * Gets JSON for object
     *
     * @return JSON
     */
    public function getMetricJson() {
        return json_encode($this);
    }


    /**
     * Required function to use the json_encode function
     *
     * @return array()
     */
    public function jsonSerialize() {
        return
            [ $this->key => $this->data ];
    }
}