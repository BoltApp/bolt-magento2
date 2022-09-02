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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\VersionNotifier;

use Bolt\Boltpay\Api\Data\PluginVersionNotificationInterface;
use Bolt\Boltpay\Model\ResourceModel\VersionNotifier\PluginVersionNotification as PluginVersionNotificationResource;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Plugin Version Notifier
 */
class PluginVersionNotification extends AbstractExtensibleModel implements PluginVersionNotificationInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(PluginVersionNotificationResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getLatestVersion()
    {
        return $this->getData(self::LATEST_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function setLatestVersion($version): PluginVersionNotificationInterface
    {
        return $this->setData(self::LATEST_VERSION, $version);
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setDescription($description): PluginVersionNotificationInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }
}
