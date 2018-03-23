<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */


namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\OrderManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Class OrderManagement
 * Web hook endpoint. Save the order / Update order and payment status.
 *
 * @package Bolt\Boltpay\Model\Api
 */
class OrderManagement implements OrderManagementInterface
{
	/**
	 * @var HookHelper
	 */
	protected $hookHelper;


	/**
     * @var OrderHelper
     */
    protected $orderHelper;


	/**
	 * @var LogHelper
	 */
	protected $logHelper;


	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var Bugsnag
	 */
	protected $bugsnag;


	/**
	 * @param HookHelper $hookHelper
	 * @param OrderHelper $orderHelper
	 * @param LogHelper $logHelper
	 * @param Request $request
	 * @param Bugsnag $bugsnag
	 */
    public function __construct(
	    HookHelper  $hookHelper,
        OrderHelper $orderHelper,
        LogHelper   $logHelper,
	    Request     $request,
	    Bugsnag     $bugsnag
    ) {
	    $this->hookHelper  = $hookHelper;
        $this->orderHelper = $orderHelper;
        $this->logHelper   = $logHelper;
	    $this->request     = $request;
	    $this->bugsnag     = $bugsnag;
    }

	/**
	 * Manage order.
	 *
	 * @api
	 *
	 * @param mixed $quote_id
	 * @param mixed $reference
	 * @param mixed $transaction_id
	 * @param mixed $notification_type
	 * @param mixed $amount
	 * @param mixed $currency
	 * @param mixed $status
	 * @param mixed $display_id
	 * @param mixed $source_transaction_id
	 * @param mixed $source_transaction_reference
	 *
	 * @return void
	 * @throws \Exception
	 */
    public function manage($quote_id = null, $reference, $transaction_id = null, $notification_type = null, $amount = null, $currency = null, $status = null, $display_id = null, $source_transaction_id = null, $source_transaction_reference = null)
    {
    	try {
		    //$this->logHelper->addInfoLog("API Hook Called");
		    $this->logHelper->addInfoLog($this->request->getContent());
		    $this->hookHelper->verifyWebhook();

		    if (empty($reference)) {
			    throw new LocalizedException(
				    __('Missing required parameters.')
			    );
		    }
		    $this->orderHelper->saveUpdateOrder($reference, false);
	    } catch ( \Exception $e ) {
			$this->bugsnag->notifyException($e);
			throw $e;
		}
    }
}
