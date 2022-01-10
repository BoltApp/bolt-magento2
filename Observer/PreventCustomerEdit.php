<?php

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class PreventCustomerEdit implements ObserverInterface
{

    /**
     * @var ActionFlag
     */
    private $actionFlag;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var UrlInterface
     */
    private $urlManager;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     * @param UrlInterface $urlManager
     * @param ManagerInterface $messageManager
     * @param Decider $featureSwitches
     */
    public function __construct(
        ActionFlag        $actionFlag,
        RedirectInterface $redirect,
        UrlInterface      $urlManager,
        ManagerInterface  $messageManager,
        Decider           $featureSwitches,
        Config $config
    ) {
        $this->actionFlag = $actionFlag;
        $this->redirect = $redirect;
        $this->urlManager = $urlManager;
        $this->messageManager = $messageManager;
        $this->featureSwitches = $featureSwitches;
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->featureSwitches->isPreventSSOCustomersFromEditingAccountInformation()
            || !$this->featureSwitches->isBoltSSOEnabled()
            || !$this->config->isBoltSSOEnabled()) {
            return;
        }

        /** @var Request $request */
        $request = $observer->getData('request');

        /** @var \Magento\Framework\App\Action\AbstractAction $action */
        $action = $observer->getData('controller_action');

        if (in_array($request->getFullActionName(), Config::PROHIBITED_CUSTOMER_ROUTES_WITH_SSO)) {
            $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
            $this->messageManager->addErrorMessage(
                __('Account editing not supported.')
            );
            $action->getResponse()->setRedirect($this->redirect->error($this->urlManager->getUrl('customer/account')));
        }
    }
}
