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

namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Helper\Bugsnag;
use Exception;

class EventsForThirdPartyModules
{
    const eventListeners = [
        "afterLoadSession" => [
            "listeners" => [
                [
                    "module" => "Mageplaza_ShippingRestriction",
                    "3pclasses" => ["Mageplaza\ShippingRestriction\Helper\Data"],
                    "boltClass" => "Bolt\Boltpay\ThirdPartyModules\Mageplaza\ShippingRestriction",
                ],
            ],
        ]
    ];

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * ThirdPartyModuleFactory constructor.
     *
     * @param \Magento\Framework\Module\Manager         $moduleManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        Bugsnag $bugsnag
    ) {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Dispatch event
     *
     * Call all listeners that relates to existing module and if necessary classes exist
     */
    public function dispatchEvent($eventName, ...$arguments) {
        if (!isset(static::eventListeners[$eventName])) {
            return;
        }
        try {
            foreach (static::eventListeners[$eventName]["listeners"] as $listener) {
                if (!$this->isModuleAvailable($listener["module"])) {
                    continue;
                }
                foreach ($listener["3pclasses"] as $className) {
                    if (!$this->isClassExists($className)) {
                        continue 2;
                    }
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                $boltClass->$eventName(...$arguments);
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Check whether the module is available (installed and enabled)
     * @return bool
     */
    private function isModuleAvailable($moduleName)
    {
        return $this->moduleManager->isEnabled($moduleName);
    }
    
    /**
     * Check whether the class exists
     * @return bool
     */
    private function isClassExists($className)
    {
        ///////////////////////////////////////////////////////////////
        // Due to a known bug https://github.com/magento/magento2/pull/21435,
        // the autoloader throws an exception on class_exists.
        // Add try-catch block to avoid uncaught exceptions during autoloading.
        // Return false instead if any uncaught exceptions.
        ///////////////////////////////////////////////////////////////
        try {
            return class_exists($className);
        } catch (\Exception $e) {
            return false;
        }
    }
}
