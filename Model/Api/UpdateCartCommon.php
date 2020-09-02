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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebApiException;
use Magento\Quote\Model\Quote;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepository;
use Magento\Directory\Model\Region as RegionModel;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Model\Api\UpdateCartContext;
use Bolt\Boltpay\Helper\ArrayHelper;

/**
 * Class UpdateCartCommon
 * 
 * @package Bolt\Boltpay\Model\Api
 */
abstract class UpdateCartCommon
{   
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnag;

    /**
     * @var CartHelper
     */
    protected $cartHelper;

    /**
     * @var HookHelper
     */
    protected $hookHelper;

    /**
     * @var BoltErrorResponse
     */
    protected $errorResponse;

    /**
     * @var RegionModel
     */
    protected $regionModel;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;
    
    /**
     * UpdateCartCommon constructor.
     *
     * @param UpdateCartContext $updateCartContext
     */
    public function __construct(
        UpdateCartContext $updateCartContext
    )
    {
        $this->request = $updateCartContext->getRequest();
        $this->response = $updateCartContext->getResponse();
        $this->hookHelper = $updateCartContext->getHookHelper();
        $this->errorResponse = $updateCartContext->getBoltErrorResponse();
        $this->logHelper = $updateCartContext->getLogHelper();   
        $this->bugsnag = $updateCartContext->getBugsnag();
        $this->regionModel = $updateCartContext->getRegionModel();
        $this->orderHelper = $updateCartContext->getOrderHelper();
        $this->cartHelper = $updateCartContext->getCartHelper();
    }
    
    /**
     * Validate the related quote.
     *
     * @param  object $request
     * @return bool
     */
    public function validateQuote($immutableQuoteId)
    {
        try {
            // check the existence of child quote
            $immutableQuote = $this->cartHelper->getQuoteById($immutableQuoteId);
            if (!$immutableQuote) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart reference [%s] is not found.', $immutableQuoteId),
                    404
                );
                return false;
            }

            $parentQuoteId = $immutableQuote->getBoltParentQuoteId();

            if(empty($parentQuoteId)) {
                $this->bugsnag->notifyError(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    'Parent quote is not exist'
                );
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    'Parent quote is not exist',
                    404
                );
                return false;
            }

            /** @var Quote $parentQuote */
            if ($immutableQuoteId == $parentQuoteId) {
                // Product Page Checkout - quotes are created as inactive
                $parentQuote = $this->cartHelper->getQuoteById($parentQuoteId);
            } else {
                $parentQuote = $this->cartHelper->getActiveQuoteById($parentQuoteId);
            }
            
            // check if the order has already been created
            if ($this->orderHelper->getExistingOrder(null, $parentQuoteId)) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The order by quote #%s has already been created ', $parentQuoteId),
                    422
                );
                return false;
            }

            // check if cart is empty
            if (!$immutableQuote->getItemsCount()) {
                $this->sendErrorResponse(
                    BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                    sprintf('The cart for order reference [%s] is empty.', $immutableQuoteId),
                    422
                );

                return false;
            }
            
            return [
                $parentQuote,
                $immutableQuote,
            ];

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->sendErrorResponse(
                BoltErrorResponse::ERR_INSUFFICIENT_INFORMATION,
                $e->getMessage(),
                404
            );
            return false;
        }
    }
    
    /**
     *
     * Set the shipment if request payload has that info.
     * 
     * @param array $shipment
     * @param Quote $immutableQuote
     * 
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function setShipment($shipment, $immutableQuote)
    {
        $shippingAddress = $immutableQuote->getShippingAddress();
        $address = $shipment['shipping_address'];
        $address = $this->cartHelper->handleSpecialAddressCases($address);
        $region = $this->regionModel->loadByName(ArrayHelper::getValueFromArray($address, 'region', ''), ArrayHelper::getValueFromArray($address, 'country_code', ''));
        $addressData = [
                    'firstname'    => ArrayHelper::getValueFromArray($address, 'first_name', ''),
                    'lastname'     => ArrayHelper::getValueFromArray($address, 'last_name', ''),
                    'street'       => trim(ArrayHelper::getValueFromArray($address, 'street_address1', '') . "\n" . ArrayHelper::getValueFromArray($address, 'street_address2', '')),
                    'city'         => ArrayHelper::getValueFromArray($address, 'locality', ''),
                    'country_id'   => ArrayHelper::getValueFromArray($address, 'country_code', ''),
                    'region'       => ArrayHelper::getValueFromArray($address, 'region', ''),
                    'postcode'     => ArrayHelper::getValueFromArray($address, 'postal_code', ''),
                    'telephone'    => ArrayHelper::getValueFromArray($address, 'phone_number', ''),
                    'region_id'    => $region ? $region->getId() : null,
                    'company'      => ArrayHelper::getValueFromArray($address, 'company', ''),
                ];
        if ($this->cartHelper->validateEmail(ArrayHelper::getValueFromArray($address, 'email_address', ''))) {
            $addressData['email'] = $address['email_address'];
        }

        $shippingAddress->setShouldIgnoreValidation(true);
        $shippingAddress->addData($addressData);

        $shippingAddress
            ->setShippingMethod($shipment['reference'])
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->save();
    }

    /**
     * @param null|int $storeId
     * @throws LocalizedException
     * @throws WebApiException
     */
    public function preProcessWebhook($storeId = null)
    {
        $this->hookHelper->preProcessWebhook($storeId);
    }

    /**
     * @return array
     */
    protected function getRequestContent()
    {
        $content =  $this->request->getContent();
        $this->logHelper->addInfoLog($content);
        return json_decode($content);
    }    

    /**
     * @param int        $errCode
     * @param string     $message
     * @param int        $httpStatusCode
     * @param null|Quote $quote
     *
     * @return void
     * @throws \Exception
     */
    abstract protected function sendErrorResponse($errCode, $message, $httpStatusCode, $quote = null);

    /**
     * @param array $result

     * @return array
     * @throws \Exception
     */
    abstract protected function sendSuccessResponse($result);

}
