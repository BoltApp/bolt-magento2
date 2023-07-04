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

namespace Bolt\Boltpay\Plugin\ClassyLlama\AvaTax\Plugin\Model\ResourceModel;

use ClassyLlama\AvaTax\Plugin\Model\ResourceModel\ExtensionAttributesPersistencePlugin;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class ExtensionAttributesPersistencePluginPlugin
 *
 * @package Bolt\Boltpay\Plugin\ClassyLlama\AvaTax\Plugin\Model\ResourceModel
 */
class ExtensionAttributesPersistencePluginPlugin
{
    /**
     * Prevents {@see ExtensionAttributesPersistencePlugin::aroundSave}
     * from being called for orders that already have avatax_response saved to prevent duplicate rows
     *
     * @param ExtensionAttributesPersistencePlugin $subject the Avatax original plugin
     * @param callable                             $proceed continuation of the plugin chain with the avatax plugin
     *                                                      being next
     * @param AbstractDb                           $nextSubject
     * @param callable                             $nextProceed
     * @param AbstractModel                        $nextObject
     *
     * @return mixed
     */
    public function aroundAroundSave(
        ExtensionAttributesPersistencePlugin $subject,
        callable $proceed,
        AbstractDb $nextSubject,
        callable $nextProceed,
        AbstractModel $nextObject
    ) {
        if ($nextObject instanceof \Magento\Sales\Model\Order) {
            $connection = $nextSubject->getConnection();
            $avataxResponses = $connection->fetchAll(
                $connection->select()
                    ->from($nextSubject->getTable('avatax_sales_order'))
                    ->where(
                        'order_id = ?',
                        $nextObject->getId()
                    )
            );
            if (count($avataxResponses) > 0) {
                /** skip @see ExtensionAttributesPersistencePlugin::aroundSave * */
                return $nextProceed($nextObject);
            }
        }
        return $proceed($nextSubject, $nextProceed, $nextObject);
    }
}
