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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Helper\Bugsnag;
use Exception;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\QuoteRepository;

class RouteInsuranceManagement implements \Bolt\Boltpay\Api\RouteInsuranceManagementInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @var Serialize
     */
    private $serializer;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @param Response $response
     * @param Bugsnag $bugsnag
     * @param Manager $moduleManager
     * @param Serialize $serializer
     * @param CartHelper $cartHelper
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        Response $response,
        Bugsnag $bugsnag,
        Manager $moduleManager,
        Serialize $serializer,
        CartHelper $cartHelper,
        QuoteRepository $quoteRepository
    ) {
        $this->response = $response;
        $this->bugsnag = $bugsnag;
        $this->moduleManager = $moduleManager;
        $this->serializer = $serializer;
        $this->cartHelper = $cartHelper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param $cartId
     * @param $routeIsInsured
     * @return void
     * @throws WebapiException
     */
    public function execute($cartId, $routeIsInsured)
    {
        try {
            if (!$this->isModuleEnabled()) {
                $responseBody = $this->serializer->serialize(
                    [
                        'message' => sprintf("%s is not installed on merchant's site", self::ROUTE_MODULE_NAME)
                    ]);
                $httpResponseCode = self::RESPONSE_FAIL_STATUS;
            } else {
                $responseBody = $this->setRouteIsInsuredToQuote($routeIsInsured, $cartId);
                $httpResponseCode = self::RESPONSE_SUCCESS_STATUS;
            }
            $this->responseBuilder($responseBody, $httpResponseCode);
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    private function setRouteIsInsuredToQuote($routeIsInsured, $cartId) {
        $quote = $this->cartHelper->getQuoteById($cartId);
        if (!$quote)
        {
            throw new WebapiException(__('Quote does not found by given ID'), 0, WebapiException::HTTP_NOT_FOUND);
        }
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        return $this->serializer->serialize(
            [
                'message' => $this->getResponseMessage($routeIsInsured),
                'grand_total' => $quote->getGrandTotal()
            ]
        );
    }

    private function getResponseMessage($routeIsInsured) {
        $routeStatusMessage = filter_var($routeIsInsured, FILTER_VALIDATE_BOOLEAN) ? "enabled":"disabled";
        return sprintf("Route insurance is %s for quote", $routeStatusMessage);
    }

    private function isModuleEnabled()
    {
        return $this->moduleManager->isEnabled(self::ROUTE_MODULE_NAME);
    }

    private function responseBuilder($responseBody, $httpResponseCode)
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHttpResponseCode($httpResponseCode);
        $this->response->setBody($responseBody);
        $this->response->sendResponse();
    }
}
