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

namespace Bolt\Boltpay\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Magento\Framework\App\Action\Context;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Data\Form\FormKey\Validator;

class DeleteCreditCard extends Action
{
    /**
     * @var \Bolt\Boltpay\Model\CustomerCreditCard
     */
    private $customerCreditCardFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * DeleteCreditCard constructor.
     * @param Context $context
     * @param Bugsnag $bugsnag
     * @param CustomerCreditCardFactory $customerCreditCardFactory
     * @param Validator $formKeyValidator
     * @param Session $customerSession
     */
    public function __construct(
        Context $context,
        Bugsnag $bugsnag,
        CustomerCreditCardFactory $customerCreditCardFactory,
        Validator $formKeyValidator,
        Session $customerSession
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->bugsnag = $bugsnag;
        $this->customerCreditCardFactory = $customerCreditCardFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            if (!$this->formKeyValidator->validate($this->getRequest())) {
                $this->messageManager->addErrorMessage(__('Invalid form key'));
                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $creditCard = $this->customerCreditCardFactory->create()->load($id);
                if ($creditCard
                    && $creditCard->getId()
                    && $this->customerSession->getCustomerId() == $creditCard->getCustomerId()
                ) {
                    $creditCard->delete();
                    $this->messageManager->addSuccessMessage(__('You deleted the Bolt credit card'));
                } else {
                    $this->messageManager->addErrorMessage(__("Credit Card doesn't exist"));
                }
            } else {
                $this->messageManager->addErrorMessage(__('Missing id parameter'));
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->messageManager->addExceptionMessage($e);
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
