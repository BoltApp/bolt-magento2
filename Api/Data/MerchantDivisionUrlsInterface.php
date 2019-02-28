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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Api\Data;

/**
 * Interface MerchantDivisionUrlsInterface
 *
 * @package Bolt\Boltpay\Api\Data
 */
interface MerchantDivisionUrlsInterface
{
    /**#@+
     * Constants defined for keys of  data array
     */
    const ID = 'entity_id';
    const DIVISION_ID = 'division_id';
    const TYPE = 'type';
    const URL = 'url';

    /**#@-*/
    /**
     * @return int
     */
    public function getId();

    /**
     * @param int $id
     * @return $this;
     */
    public function setId($id);

    /**
     * @return int
     */
    public function getDivisionId();

    /**
     * @param int $id
     * @return $this
     */
    public function setDivisionId($id);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type);

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl($url);
}
