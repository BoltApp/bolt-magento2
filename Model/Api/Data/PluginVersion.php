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

/**
 * Class PluginVersion. Represents a single plugin version object.
 *
 * @package Bolt\Boltpay\Model\Api\Data
 */
class PluginVersion implements \JsonSerializable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * Get plugin name.
     *
     * @return string
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set plugin name.
     *
     * @param string $name
     *
     * @return $this
     * @api
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get plugin name.
     *
     * @return string
     * @api
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set plugin version.
     *
     * @param string $version
     *
     * @return $this
     * @api
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
