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

namespace Bolt\Boltpay\Model\ThirdParty;

class ConverterForThirdPartyModules implements \Magento\Framework\Config\ConverterInterface
{
    /**
     * Convert dom node tree to array
     *
     * @param \DOMDocument $source
     * @return array
     * @throws \InvalidArgumentException
     */
    public function convert($source)
    {
        $output = [];
        /** @var \DOMNodeList $events */
        $events = $source->getElementsByTagName('event');
        /** @var \DOMNode $eventConfig */
        foreach ($events as $eventConfig) {
            $eventName = $eventConfig->attributes->getNamedItem('name')->nodeValue;
            $eventObservers = [];
            /** @var \DOMNode $observerConfig */
            foreach ($eventConfig->childNodes as $observerConfig) {
                if ($observerConfig->nodeName != 'observer' || $observerConfig->nodeType != XML_ELEMENT_NODE) {
                    continue;
                }
                $observerNameNode = $observerConfig->attributes->getNamedItem('name');
                if (!$observerNameNode) {
                    throw new \InvalidArgumentException('Attribute name is missed');
                }
                $config = $this->convertObserverConfig($observerConfig);
                $config['name'] = $observerNameNode->nodeValue;
                $config['type'] = 'event';
                list($config['check_classes'], $config['send_classes']) = $this->convertObserverClasses($observerConfig->childNodes);
                $eventObservers[$observerNameNode->nodeValue] = $config;
            }
            $output[mb_strtolower($eventName)] = isset($output[mb_strtolower($eventName)])
                                                 ? array_merge($eventObservers, $output[mb_strtolower($eventName)])
                                                 : $eventObservers;
        }
        
        $filters = $source->getElementsByTagName('filter');
        foreach ($filters as $filterConfig) {
            $filterName = $filterConfig->attributes->getNamedItem('name')->nodeValue;
            $filterObservers = [];
            /** @var \DOMNode $observerConfig */
            foreach ($filterConfig->childNodes as $observerConfig) {
                if ($observerConfig->nodeName != 'observer' || $observerConfig->nodeType != XML_ELEMENT_NODE) {
                    continue;
                }
                $observerNameNode = $observerConfig->attributes->getNamedItem('name');
                if (!$observerNameNode) {
                    throw new \InvalidArgumentException('Attribute name is missed');
                }
                $config = $this->convertObserverConfig($observerConfig);
                $config['name'] = $observerNameNode->nodeValue;
                $config['type'] = 'filter';
                list($config['check_classes'], $config['send_classes']) = $this->convertObserverClasses($observerConfig->childNodes);
                $filterObservers[$observerNameNode->nodeValue] = $config;
            }
            $output[mb_strtolower($filterName)] = isset($output[mb_strtolower($filterName)])
                                                  ? array_merge($filterObservers, $output[mb_strtolower($filterName)])
                                                  : $filterObservers;
        }

        return $output;
    }
    
    protected function convertObserverClasses($observerClasses)
    {
        $check_classes = [];
        $send_classes =[];
        foreach ($observerClasses as $observerClass) {
            if ($observerClass->nodeType != XML_ELEMENT_NODE) {
                continue;
            }
            if ($observerClass->nodeName == 'check_class') {
                $check_classes[] = $observerClass->attributes->getNamedItem('instance')->nodeValue;
            } elseif ($observerClass->nodeName == 'send_class') {
                $send_classes[] = $observerClass->attributes->getNamedItem('instance')->nodeValue;
            }
        }
        
        return [$check_classes, $send_classes];
    }

    /**
     * Convert observer configuration
     *
     * @param \DOMNode $observerConfig
     * @return array
     */
    protected function convertObserverConfig($observerConfig)
    {
        $output = [];
        /** Parse instance configuration */
        $instanceAttribute = $observerConfig->attributes->getNamedItem('instance');
        if ($instanceAttribute) {
            $output['instance'] = $instanceAttribute->nodeValue;
        }

        /** Parse instance method configuration */
        $methodAttribute = $observerConfig->attributes->getNamedItem('module');
        if ($methodAttribute) {
            $output['module'] = $methodAttribute->nodeValue;
        }

        /** Parse disabled/enabled configuration */
        $disabledAttribute = $observerConfig->attributes->getNamedItem('disabled');
        if ($disabledAttribute && $disabledAttribute->nodeValue == 'true') {
            $output['disabled'] = true;
        }

        return $output;
    }
}
