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

namespace Bolt\Boltpay\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Bolt\Boltpay\Model\CustomerCreditCardFactory;
use Bolt\Boltpay\Helper\Bugsnag;

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
     * DeleteCreditCard constructor.
     * @param Action\Context $context
     * @param Bugsnag $bugsnag
     * @param CustomerCreditCardFactory $customerCreditCardFactory
     */
    public function __construct(
        Action\Context $context,
        Bugsnag $bugsnag,
        CustomerCreditCardFactory $customerCreditCardFactory
    ) {
        $this->bugsnag = $bugsnag;
        $this->customerCreditCardFactory = $customerCreditCardFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $creditCard = $this->customerCreditCardFactory->create()->load($id);

                if ($creditCard && $creditCard->getId()) {
                    $creditCard->delete();
                    $this->messageManager->addSuccessMessage('Deleted bolt credit card successfully');
                } else {
                    $this->messageManager->addErrorMessage("Credit Card doesn't exist");
                }
            } else {
                $this->messageManager->addErrorMessage('Missing id parameter');
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->messageManager->addExceptionMessage($e);
        }
        $this->_redirect($this->_redirect->getRefererUrl());
    }
}
