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

use Bolt\Boltpay\ThirdPartyModules\Aheadworks\Giftcard as Aheadworks_Giftcard;
use Bolt\Boltpay\ThirdPartyModules\Mageplaza\ShippingRestriction as Mageplaza_ShippingRestriction;
use Bolt\Boltpay\Helper\Bugsnag;
use Exception;

class EventsForThirdPartyModules
{
    const eventListeners = [
        "afterLoadSession" => [
            "listeners" => [
                [
                    "module" => "Mageplaza_ShippingRestriction",
                    "checkClasses" => ["Mageplaza\ShippingRestriction\Helper\Data"],
                    "boltClass" => Mageplaza_ShippingRestriction::class,
                ],
            ],
        ]
    ];

    const filterListeners = [
        "applyGiftcard" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => [
                        "Aheadworks\Giftcard\Api\GiftcardCartManagementInterface",
                    ],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
            ],
        ],
        "collectDiscounts" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardCartManagementInterface"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
            ],
        ],
        "loadGiftcard" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardRepositoryInterface"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
            ],
        ],
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
     * Check if module enables and neccessary classes exist
     * return [$result, $sendClasses]
     * bool $result true if we should run the method
     * array $sendClasses array of classed we should pass into the method
     */
    private function prepareForListenerRun($listener)
    {
        if (!$this->isModuleAvailable($listener["module"])) {
            return [false, null];
        }
        if (isset($listener["checkClasses"])) {
            foreach ($listener["checkClasses"] as $className) {
                if (!$this->doesClassExist($className)) {
                    return [false,null];
                }
            }
        }
        $sendClasses = [];
        if (isset($listener["sendClasses"])) {
            foreach ($listener["sendClasses"] as $className) {
                if (!$this->doesClassExist($className)) {
                    return [false,null];
                }
                $sendClasses[] = $this->objectManager->get($className);
            }
        }
        return [true, $sendClasses];
    }

    /**
     * Run filter
     *
     * Call all filter listeners that relates to existing module and if necessary classes exist
     */
    public function runFilter($filterName, $result, ...$arguments) {
        if (!isset(static::filterListeners[$filterName])) {
            return;
        }
        try {
            foreach (static::filterListeners[$filterName]["listeners"] as $listener) {
                list ($active, $sendClasses) = $this->prepareForListenerRun($listener);
                if (!$active) {
                    continue;
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                if ($sendClasses) {
                    $result = $boltClass->$filterName($result, ...$sendClasses, ...$arguments);
                } else {
                    $result = $boltClass->$filterName($result, ...$arguments);
                }
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $result;
        }
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
                list ($active, $sendClasses) = $this->prepareForListenerRun($listener);
                if (!$active) {
                    continue;
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                if ($sendClasses) {
                    $boltClass->$eventName(...$sendClasses, ...$arguments);
                } else {
                    $boltClass->$eventName(...$arguments);
                }
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
    private function doesClassExist($className)
    {
        ///////////////////////////////////////////////////////////////
        // Due to a known bug https://github.com/magento/magento2/pull/21435,
        // the autoloader throws an exception on class_exists.
        // Add try-catch block to avoid uncaught exceptions during autoloading.
        // Return false instead if any uncaught exceptions.
        ///////////////////////////////////////////////////////////////
        try {
            if (substr($className, -9) === "Interface") {
                return interface_exists($className);
            } else {
                return class_exists($className);
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
