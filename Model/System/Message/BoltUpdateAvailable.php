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

namespace Bolt\Boltpay\Model\System\Message;

use Bolt\Boltpay\Model\Updater;

/**
 * Class BoltUpdateAvailable
 * @package Bolt\Boltpay\Model\System\Message
 */
class BoltUpdateAvailable implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var Updater
     */
    private $updater;

    /**
     * BoltUpdateAvailable constructor.
     * @param Updater $updater
     */
    public function __construct(Updater $updater)
    {
        $this->updater = $updater;
    }

    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return 'BOLT_UPDATE_AVAILABLE';
    }

    /**
     * Check whether
     *
     * @return bool
     */
    public function isDisplayed()
    {
        return $this->updater->getIsUpdateAvailable();
    }

    /**
     * Retrieve message text
     *
     * @return string
     */
    public function getText()
    {
        return $this->updater->getUpdateTitle();
    }

    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return $this->updater->getUpdateSeverity();
    }
}