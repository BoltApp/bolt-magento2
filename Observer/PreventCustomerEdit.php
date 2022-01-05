<?php

namespace Bolt\Boltpay\Observer;

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
        Decider           $featureSwitches
    ) {
        $this->actionFlag = $actionFlag;
        $this->redirect = $redirect;
        $this->urlManager = $urlManager;
        $this->messageManager = $messageManager;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->featureSwitches->isPreventSSOCustomersFromEditingAccountInformation()) {
            return;
        }

        /** @var Request $request */
        $request = $observer->getData('request');

        /** @var \Magento\Framework\App\Action\AbstractAction $action */
        $action = $observer->getData('controller_action');

        if (in_array(
            $request->getFullActionName(),
            array_merge(
                preg_filter('/^/', 'customer_account_', ['createPost', 'edit', 'editPost', 'resetPasswordPost']),
                preg_filter('/^/', 'customer_address_', ['delete', 'edit', 'form', 'formPost', 'new'])
            )
        )) {
            $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
            $this->messageManager->addErrorMessage(
                __('Account editing not supported for accounts created by Bolt SSO.')
            );
            $action->getResponse()->setRedirect($this->redirect->error($this->urlManager->getUrl('customer/account')));
        }
    }
}