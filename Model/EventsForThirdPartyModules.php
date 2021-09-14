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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Model\ThirdParty\ConfigForThirdPartyModules as Config;
use Bolt\Boltpay\Model\ThirdParty\FilterInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

class EventsForThirdPartyModules
{
    /**
     * @var Bolt\Boltpay\Model\ThirdParty\ConfigForThirdPartyModules
     */
    protected $eventConfig;
    
    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;
    
    /**
     * @var \Magento\Framework\Event\ObserverFactory
     */
    protected $observerFactory;
    
    /**
     * EventsForThirdPartyModules constructor.
     *
     */
    public function __construct(
        ObserverFactory $observerFactory,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager,
        Bugsnag $bugsnag,
        Config $eventConfig
    ) {
        $this->observerFactory = $observerFactory;
        $this->eventConfig = $eventConfig;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->bugsnag = $bugsnag;
    }
    
    /**
     * Dispatch event
     *
     * Calls all observer callbacks registered for this event
     * and multiple observers matching event name pattern
     *
     * @param string $eventName
     * @param array $data
     * @return void
     */
    public function dispatch($eventName, array $data = [])
    {
        $eventName = mb_strtolower($eventName);
        foreach ($this->eventConfig->getObservers($eventName) as $observerConfig) {
            list ($active, $sendClasses) = $this->prepareForListenerRun($observerConfig);
            if (!$active) {
                continue;
            }
            $event = new Event($data);
            $event->setName($eventName);
            $wrapper = new Observer();
            $extraData = [
                'send_classes' => $sendClasses,
            ];
            $wrapper->setData(array_merge(['event' => $event], $data, $extraData));
            $this->callEventMethod($observerConfig, $wrapper);
        }
    }
    
    /**
     * Call event method
     *
     * @param array $configuration
     * @param Observer $observer
     * @return void
     */
    protected function callEventMethod(array $configuration, Observer $observer)
    { 
        if (isset($configuration['disabled']) && true === $configuration['disabled']) {
            return;
        }
        if (!isset($configuration['type']) || 'event' !== $configuration['type']) {
            return;
        }
        $object = $this->observerFactory->get($configuration['instance']);
        if ($object instanceof ObserverInterface) {         
            $object->execute($observer);
        }
    }
    
    /**
     * Apply filter
     *
     * @param string $filterName
     * @param mixed $result
     * @param array $data
     * @return mixed
     */
    public function filter($filterName, $result, array $data = [])
    {
        $filterName = mb_strtolower($filterName);
        foreach ($this->eventConfig->getObservers($filterName) as $observerConfig) {
            list ($active, $sendClasses) = $this->prepareForListenerRun($observerConfig);
            if (!$active) {
                continue;
            }
            $event = new Event($data);
            $event->setName($filterName);
            $wrapper = new Observer();
            $extraData = [
                'send_classes' => $sendClasses,
            ];
            $wrapper->setData(array_merge(['event' => $event], ['result' => $result], $data, $extraData));
            $result = $this->callFilterMethod($observerConfig, $wrapper, $result);
        }
        
        return $result;
    }
    
    /**
     * Call filter method
     *
     * @param array $configuration
     * @param Observer $observer
     * @param mixed $result
     * @return mixed
     */
    protected function callFilterMethod(array $configuration, Observer $observer, $result)
    {
        if (isset($configuration['disabled']) && true === $configuration['disabled']) {
            return $result;
        }
        if (!isset($configuration['type']) || 'filter' !== $configuration['type']) {
            return $result;
        }
        $object = $this->objectManager->get($configuration['instance']);
        if ($object instanceof FilterInterface) {         
            $result = $object->execute($observer);
        }

        return $result;
    }
    
    /**
     * Check if module enables and neccessary classes exist
     * return [$result, $sendClasses]
     * bool $result true if we should run the method
     * array $sendClasses array of classed we should pass into the method
     */
    protected function prepareForListenerRun($listener, $createInstance = true)
    {
        if (!$this->isModuleAvailable($listener["module"]) && empty($listener["always_run"])) {
            return [false, null];
        }
        if (isset($listener["check_classes"])) {
            foreach ($listener["check_classes"] as $classNameItem) {
                // Some merchants still use legacy version of third-party plugin,
                // so there are cases that the class does not exist,
                // then we use sub-array to include classes for supported versions.
                $classNames = is_array($classNameItem) ? $classNameItem : [$classNameItem];
                $existClasses = array_filter($classNames, function ($className) {
                    return $this->doesClassExist($className);
                });
                if (empty($existClasses)) {
                    return [false,null];
                }
            }
        }
        $sendClasses = [];
        if (isset($listener["send_classes"])) {
            foreach ($listener["send_classes"] as $classNameItem) {
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
     * Check whether the module is available (installed and enabled)
     * @return bool
     */
    public function isModuleAvailable($moduleName)
    {
        return $this->moduleManager->isEnabled($moduleName);
    }

    /**
     * Check whether the class exists
     * @return bool
     */
    protected function doesClassExist($className)
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
    
    /**
     * Remove when all the ThirdPartyModules are moved to magento2-modules-support
     */
    public function runFilter($filterName, $result, ...$arguments)
    {
        return $result;
    }
    
    /**
     * Remove when all the ThirdPartyModules are moved to magento2-modules-support
     */
    public function dispatchEvent($eventName, ...$arguments)
    {
        return;
    }
}
