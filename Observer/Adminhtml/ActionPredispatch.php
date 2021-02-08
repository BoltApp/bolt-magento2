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

namespace Bolt\Boltpay\Observer\Adminhtml;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

/**
 * Observer for controller_action_predispatch
 * @see \Magento\Framework\App\Action\Action::dispatch
 */
class ActionPredispatch implements ObserverInterface
{
    /**
     * @var \Bolt\Boltpay\Model\EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * ActionPredispatch constructor.
     * @param \Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(\Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules)
    {
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }

    /**
     * Redirects the predispatch event to the {@see \Bolt\Boltpay\Model\EventsForThirdPartyModules}
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\App\RequestInterface $request */
        $request = $observer->getData('request');
        $this->eventsForThirdPartyModules->dispatchEvent(
            $this->convertEventName('adminhtml_controller_action_predispatch'),
            $observer
        );
        $this->eventsForThirdPartyModules->dispatchEvent(
            $this->convertEventName('adminhtml_controller_action_predispatch_' . $request->getRouteName()),
            $observer
        );
        $this->eventsForThirdPartyModules->dispatchEvent(
            $this->convertEventName('adminhtml_controller_action_predispatch_' . $request->getFullActionName()),
            $observer
        );
    }

    /**
     * Converts Magento format event name to the Boltpay format used in
     * @see \Bolt\Boltpay\Model\EventsForThirdPartyModules
     *
     * @param string $eventName in Magento format
     * @return string event name in Boltpay third party modules event format
     */
    private function convertEventName($eventName)
    {
        return lcfirst(str_replace('_', '', ucwords($eventName, '_')));
    }
}
