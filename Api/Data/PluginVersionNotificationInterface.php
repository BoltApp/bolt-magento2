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

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * @api
 */
interface PluginVersionNotificationInterface extends ExtensibleDataInterface
{
    public const LATEST_VERSION = 'latest_version';
    public const DESCRIPTION = 'description';

    /**
     * Get latest version.
     *
     * @api
     * @return string
     */
    public function getLatestVersion();

    /**
     * Set latest version.
     *
     * @api
     * @param $version
     *
     * @return PluginVersionNotificationInterface
     */
    public function setLatestVersion($version): PluginVersionNotificationInterface;

    /**
     * Get release description.
     *
     * @api
     * @return string
     */
    public function getDescription();

    /**
     * Set release description.
     *
     * @api
     * @param $description
     *
     * @return PluginVersionNotificationInterface
     */
    public function setDescription($description): PluginVersionNotificationInterface;
}
