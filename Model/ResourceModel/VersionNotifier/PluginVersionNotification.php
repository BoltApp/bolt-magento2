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

namespace Bolt\Boltpay\Model\ResourceModel\VersionNotifier;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Bolt\Boltpay\Api\Data\PluginVersionNotificationInterface;

/**
 * Plugin Version Notification resource
 */
class PluginVersionNotification extends AbstractDb
{
    protected $_isPkAutoIncrement = false;
    /**
     * PluginVersionNotification Resource initialization
     * @return void
     */
    protected function _construct()
    {
        $this->_init('plugin_version_notification', PluginVersionNotificationInterface::LATEST_VERSION);
    }
}
