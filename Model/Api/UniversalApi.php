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

use Bolt\Boltpay\Api\UniversalApiInterface;
use Bolt\Boltpay\Api\CreateOrderInterface;
use Bolt\Boltpay\Api\DiscountCodeValidationInterface;
use Bolt\Boltpay\Api\OrderManagementInterface;
use Bolt\Boltpay\Api\ShippingInterface;
use Bolt\Boltpay\Api\ShippingMethodsInterface;
use Bolt\Boltpay\Api\TaxInterface;
use Bolt\Boltpay\Api\UpdateCartInterface;
use Bolt\Boltpay\Api\Data\UniversalApiResultInterface;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Magento\Framework\Webapi\Rest\Response;

class UniversalApi implements UniversalApiInterface
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
     * @var ShippingInterface
     */
    protected $shipping;

    /**
     * @var ShippingMethodsInterface
     */
    protected $shippingMethods;

    /**
     * @var TaxInterface
     */
    protected $tax;

    /**
     * @var UpdateCartInterface
     */
    protected $updateCart;

    /**
     * @var UniversalApiResultInterface
     */
    protected $result;

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
        ShippingInterface $shipping,
        ShippingMethodsInterface $shippingMethods,
        TaxInterface $tax,
        UpdateCartInterface $updateCart,
        UniversalApiResultInterface $result,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        BoltErrorResponse $errorResponse,
        Response $response
    )
    {
        $this->createOrder = $createOrder;
        $this->discountCodeValidation = $discountCodeValidation;
        $this->orderManagement = $orderManagement;
        $this->shipping = $shipping;
        $this->shippingMethods = $shippingMethods;
        $this->tax = $tax;
        $this->updateCart = $updateCart;
        $this->result = $result;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->errorResponse = $errorResponse;
        $this->response = $response;
    }

    public function execute(
        $event = null,
        $data = null
    )
    {
        try {
            switch($event){
                case "order.create":
                    //currently sends its own response updated for v2, no return
                    $this->createOrder->execute(
                        $event,
                        isset($data['order']) ? $data['order'] : null,
                        isset($data['currency']) ? $data['currency'] : null
                    );
                    break;
                case "cart.create":
                    //sends response itself, updated for v2
                    $this->orderManagement->createCart(
                        isset($data['items']) ? $data['items'] : null,
                        isset($data['currency']) ? $data['currency'] : null
                    );
                    break;
                case "manage_order": 
                    //this will be moved to the webhooks side I believe
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
                    //may not be necessary here.
                    $this->discountCodeValidation->validate();
                    break;
                case "discounts.code.apply":
                    //not yet functional
                    $this->result->setData(
                        $this->updateCart->execute(
                            isset($data['cart']) ? $data['cart'] : null,
                            isset($data['discount_codes_to_add']) ? $data['discount_codes_to_add'] : null
                        )
                    );
                    break;
                case "cart.update":
                    //returns a value if v2. in the v1 case still just sends its own response
                    $this->result->setData(
                        $this->updateCart->execute(
                            isset($data['cart']) ? $data['cart'] : null,
                            isset($data['add_items']) ? $data['add_items'] : null,
                            isset($data['remove_items']) ? $data['remove_items'] : null,
                            isset($data['discount_codes_to_add']) ? $data['discount_codes_to_add'] : null,
                            isset($data['discount_codes_to_remove']) ? $data['discount_codes_to_remove'] : null
                        )
                    );
                    break;
                case "order.shipping_and_tax":
                    //Returns ShippingOptionsInterface
                    $this->result->setData(
                        $this->shippingMethods->getShippingMethods(
                            isset($data['cart']) ? $data['cart'] : null,
                            isset($data['shipping_address']) ? $data['shipping_address'] : null
                        )
                    );
                    break;
                case "order.shipping":
                    //Returns ShippingDataInterface
                    $this->shipping->execute(
                        isset($data['cart']) ? $data['cart'] : null,
                        isset($data['shipping_address']) ? $data['shipping_address'] : null,
                        isset($data['shipping_option']) ? $data['shipping_option'] : null
                    );
                    break;
                case "order.tax":
                    //Returns TaxDataInterface
                    $this->tax->execute(
                        isset($data['cart']) ? $data['cart'] : null,
                        isset($data['shipping_address']) ? $data['shipping_address'] : null,
                        isset($data['shipping_option']) ? $data['shipping_option'] : null
                    );
                    break;
                default:
                    throw new BoltException(
                        __('Invalid webhook type %1', $event),
                        null,
                        BoltErrorResponse::ERR_SERVICE
                    );
                    break;
            }

            //not everything returns a value for result here. They send their own responses for now.
            //TODO: fix this so that everything returns an interface to this class
            if ($this->result->getData() != null) {
                $this->result->setEvent($event);
                $this->result->setStatus("success");

                return $this->result;
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

        // return true;
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