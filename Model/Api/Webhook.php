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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\WebhookInterface;
use Bolt\Boltpay\Api\CreateOrderInterface;
use Bolt\Boltpay\Api\DiscountCodeValidationInterface;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Api\UpdateCartInterface;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Rest\Response;

class Webhook implements WebhookInterface
{
    /**
     * @var CreateOrderInterface
     */
    protected $createOrder;

    /**
     * @var DiscountCodeValidationInterface
     */
    protected $discountCodeValidation;
    
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var UpdateCartInterface
     */
    protected $updateCart;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var Response
     */
    protected $response;

    public function __construct(
        CreateOrderInterface $createOrder,
        DiscountCodeValidationInterface $discountCodeValidation,
        OrderManagementInterface $orderManagement,
        UpdateCartInterface $updateCart,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        BoltErrorResponse $errorResponse,
        Response $response
    )
    {
        $this->createOrder = $createOrder;
        $this->discountCodeValidation = $discountCodeValidation;
        $this->orderManagement = $orderManagement;
        $this->updateCart = $updateCart;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->errorResponse = $errorResponse;
        $this->response = $response;
    }

    public function execute(
        $type = null,
        $data = null
    )
    {
        try {
            switch($type){
                case "create_order":
                    $this->createOrder->execute(
                        isset($data['type']) ? $data['type'] : null,
                        isset($data['order']) ? $data['order'] : null,
                        isset($data['currency']) ? $data['currency'] : null
                    );
                    break;
                case "manage_order":
                    $this->orderManagement->manage(
                        isset($data['id']) ? $data['id'] : null,
                        isset($data['reference']) ? $data['reference'] : null,
                        isset($data['order']) ? $data['order'] : null,
                        isset($data['type']) ? $data['type'] : null,
                        isset($data['amount']) ? $data['amount'] : null,
                        isset($data['currency']) ? $data['currency'] : null,
                        isset($data['status']) ? $data['status'] : null,
                        isset($data['display_id']) ? $data['display_id'] : null
                    );
                    break;
                case "validate_discount":
                    $this->discountCodeValidation->validate();
                    break;
                case "cart_update":
                    $this->updateCart->execute(
                        isset($data['cart']) ? $data['cart'] : null,
                        isset($data['add_items']) ? $data['add_items'] : null,
                        isset($data['remove_items']) ? $data['remove_items'] : null,
                        isset($data['discount_codes_to_add']) ? $data['discount_codes_to_add'] : null,
                        isset($data['discount_codes_to_remove']) ? $data['discount_codes_to_remove'] : null
                    );
                    break;
                case "shipping_methods":
                    break;
                case "shipping_options":
                    break;
                case "tax":
                    break;
                default:
                    throw new BoltException(
                        __('Invalid webhook type %1', $type),
                        null,
                        BoltErrorResponse::ERR_SERVICE
                    );
                    break;
            }

        }
        catch (BoltException $e) {
            $this->sendErrorResponse(
                $e->getCode(),
                $e->getMessage(),
                422
            );
            return false;
        }
        catch (\Exception $e) {
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_SERVICE,
                $e->getMessage(),
                500
            );
            return false;
        }

        return true;
    }

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     *
     * @return void
     * @throws \Exception
     */
    protected function sendErrorResponse($errCode, $message, $httpStatusCode)
    {
        //TODO: additional error response according to handler being called
        $additionalErrorResponseData = [];

        $encodeErrorResult = $this->errorResponse
            ->prepareErrorMessage($errCode, $message, $additionalErrorResponseData);

        $this->logHelper->addInfoLog('### sendErrorResponse');
        $this->logHelper->addInfoLog($encodeErrorResult);
        
        $this->bugsnag->notifyException(new \Exception($message));

        $this->response->setHttpResponseCode($httpStatusCode);
        $this->response->setBody($encodeErrorResult);
        $this->response->sendResponse();
    }
}