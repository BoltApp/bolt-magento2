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

namespace Bolt\Boltpay\Plugin\Magento\Framework\App\Action;

use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Class AbstractActionPlugin
 * @package Bolt\Boltpay\Plugin\Magento\Framework\App\Action
 */
class AbstractActionPlugin
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    
    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;
    
    /**
     * @var Bolt\Boltpay\Model\EventsForThirdPartyModules
     */
    protected $eventsForThirdPartyModules;

    /**
     * AbstractActionPlugin constructor.
     * @param Bolt\Boltpay\Model\EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Http\Context $httpContext
     */
    public function __construct(
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        Session $customerSession,
        Context $httpContext
    ) {
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
    }

    /**
     * @param \Magento\Framework\App\ActionInterface $subject
     * @param callable $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function aroundDispatch(
        ActionInterface $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->eventsForThirdPartyModules->dispatchEvent("shouldDisableBoltCheckout", $this->customerSession, $this->httpContext);
        return $proceed($request);
    }
}
