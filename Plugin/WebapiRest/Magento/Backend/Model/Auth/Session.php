<?php

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Backend\Model\Auth;

use Magento\Framework\Webapi\Rest\Request as WebApiRequest;

class Session
{
    /**
     * @var WebApiRequest
     */
    protected $webApiRequest;

    /** @var \Magento\Backend\Model\Auth\Session $backendSession */
    protected $backendSession;

    /** @var \Magento\Framework\Module\Manager $moduleManager */
    protected $moduleManager;

    /**
     * @param WebApiRequest $webApiRequest
     * @param \Magento\Backend\Model\Auth\Session $backendSession
     * @param \Magento\Framework\Module\Manager $moduleManager
     */
    public function __construct(
        WebApiRequest                       $webApiRequest,
        \Magento\Backend\Model\Auth\Session $backendSession,
        \Magento\Framework\Module\Manager   $moduleManager
    )
    {
        $this->webApiRequest = $webApiRequest;
        $this->backendSession = $backendSession;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param \Magento\Backend\Model\Auth\Session $object
     * @param $result
     * @return mixed|true
     */
    public function afterIsLoggedIn(\Magento\Backend\Model\Auth\Session $object, $result)
    {
        // verify if api request is from Bolt side
        if ($this->webApiRequest->getHeader('x-bolt-trace-id')) {
            // verify if Mageside_CustomShippingPrice module is enabled
            if ($this->moduleManager->isEnabled("Mageside_CustomShippingPrice")) {
                if ($this->backendSession->getIsBoltBackOffice()) {
                    return true;
                }
            }
        }
        return $result;
    }
}
