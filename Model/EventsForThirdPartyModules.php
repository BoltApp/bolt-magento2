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
use Bolt\Boltpay\ThirdPartyModules\Mirasvit\Credit as Mirasvit_Credit;
use Bolt\Boltpay\ThirdPartyModules\IDme\GroupVerification as IDme_GroupVerification;
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
        ],
        "beforeDeleteOrder" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["\Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
            ],
        ],
        'beforeApplyDiscount' => [
            "listeners" => [
                [
                    "module" => "IDme_GroupVerification",
                    "boltClass" => IDme_GroupVerification::class,
                ],
            ]
        ]
    ];

    const filterListeners = [
        "applyGiftcard" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardCartManagementInterface"],
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
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => ["Mirasvit\Credit\Helper\Data",
                                      "Mirasvit\Credit\Service\Calculation",
                                      // For old version of Mirasvit Store Credit plugin,
                                      // \Magento\Framework\ObjectManagerInterface can not create instance of \Mirasvit\Credit\Api\Config\CalculationConfigInterface properly,
                                      // so we use \Mirasvit\Credit\Service\Config\CalculationConfig instead.
                                      ["Mirasvit\Credit\Api\Config\CalculationConfigInterface",
                                      "Mirasvit\Credit\Service\Config\CalculationConfig"],],
                    "boltClass" => Mirasvit_Credit::class,
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
        "collectShippingDiscounts" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => ["Mirasvit\Credit\Helper\Data"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
            ],
        ],
        "checkMirasvitCreditAdminQuoteUsed" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "checkClasses" => ["Mirasvit\Credit\Model\Config"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
            ],
        ],
        "checkMirasvitCreditIsShippingTaxIncluded" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => [["Mirasvit\Credit\Api\Config\CalculationConfigInterface",
                                      "Mirasvit\Credit\Service\Config\CalculationConfig"]],
                    "boltClass" => Mirasvit_Credit::class,
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
    private function prepareForListenerRun($listener, $createInstance = true)
    {
        if (!$this->isModuleAvailable($listener["module"])) {
            return [false, null];
        }
        if (isset($listener["checkClasses"])) {
            foreach ($listener["checkClasses"] as $classNameItem) {
                // Some merchants still use legacy version of third-party plugin,
                // so there are cases that the class does not exist,
                // then we use sub-array to include classes for supported versions.
                $classNames = is_array($classNameItem) ? $classNameItem : [$classNameItem];
                $existClasses = array_filter($classNames, function($className) {
                    return $this->doesClassExist($className);
                });
                if (empty($existClasses)) {
                    return [false,null];
                }                
            }
        }
        $sendClasses = [];
        if (isset($listener["sendClasses"])) {
            foreach ($listener["sendClasses"] as $classNameItem) {
                // Some merchants still use legacy version of third-party plugin,
                // so there are cases that the class does not exist or its instance can not be created,
                // then we use sub-array to include classes for supported versions.
                $classNames = is_array($classNameItem) ? $classNameItem : [$classNameItem];
                $classInstance = null;
                foreach ($classNames as $className) {
                    // Once the class instance is created, no need to process the rest.
                    if (!empty($classInstance)) {
                        break;
                    }
                    $classInstance = $this->doesClassExist($className) ? $this->objectManager->get($className) : null;
                }

                if (empty($classInstance)) {
                    return [false,null];
                }

                $sendClasses[] = $classInstance;               
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
                // By default, we create instance for running filter
                $createInstance = isset($listener["createInstance"]) ? $listener["createInstance"] : true;
                list ($active, $sendClasses) = $this->prepareForListenerRun($listener, $createInstance);
                if (!$active) {
                    continue;
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                if ($createInstance && $sendClasses) {
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
