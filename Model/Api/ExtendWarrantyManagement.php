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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\Data\ExtendWarrantyPlanInterface;
use Bolt\Boltpay\Api\ExtendWarrantyManagementInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Extend_Warranty module management data
 */
class ExtendWarrantyManagement implements ExtendWarrantyManagementInterface
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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param Response $response
     * @param Bugsnag $bugsnag
     * @param Manager $moduleManager
     * @param Serialize $serializer
     * @param CartRepositoryInterface $cartRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Response $response,
        Bugsnag $bugsnag,
        Manager $moduleManager,
        Serialize $serializer,
        CartRepositoryInterface $cartRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository
    ) {
        $this->response = $response;
        $this->bugsnag = $bugsnag;
        $this->moduleManager = $moduleManager;
        $this->serializer = $serializer;
        $this->cartRepository = $cartRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
    }

    /**
     * Add extend warranty product to the cart based on extend warranty data
     *
     * @param string $cartId
     * @param ExtendWarrantyPlanInterface $plan
     * @return void
     * @throws WebapiException
     */
    public function addWarrantyPlan(string $cartId, ExtendWarrantyPlanInterface $plan)
    {
        try {
            if (!$this->isModuleEnabled()) {
                $responseBody = [
                    'message' => sprintf("%s is not installed on merchant's site", self::MODULE_NAME)
                ];
                $httpResponseCode = self::RESPONSE_FAIL_STATUS;
            } else {
                $cart = $this->cartRepository->get($cartId);
                $warrantyProduct = $this->getWarrantyProduct();
                $orderItem = $cart->addProduct($warrantyProduct, $plan->getBuyRequest());
                $this->cartRepository->save($cart);
                $responseBody = [
                    'product_id' => $orderItem->getProduct()->getId(),
                    'sku' => $orderItem->getProduct()->getSku(),
                    'name' => $orderItem->getName(),
                    'qty' => $orderItem->getQty(),
                    'type' => $orderItem->getProductType(),
                    'price' => $orderItem->getPrice(),
                    'currency' => $cart->getCurrency()->getQuoteCurrencyCode()
                ];
                $httpResponseCode = self::RESPONSE_SUCCESS_STATUS;
            }
            $this->responseBuilder($responseBody, $httpResponseCode);
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Search and return warranty product
     *
     * @return ProductInterface|null
     */
    private function getWarrantyProduct(): ?ProductInterface
    {
        $this->searchCriteriaBuilder
            ->setPageSize(1)->addFilter('type_id', self::WARRANTY_PRODUCT_TYPE);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->productRepository->getList($searchCriteria);
        $results = $searchResults->getItems();
        return reset($results);
    }

    /**
     * Checking if Extend_Warranty module enabled
     *
     * @return bool
     */
    private function isModuleEnabled()
    {
        return $this->moduleManager->isEnabled(self::MODULE_NAME);
    }

    /**
     * Build response
     *
     * @param $responseBody
     * @param $httpResponseCode
     * @return void
     */
    private function responseBuilder($responseBody, $httpResponseCode)
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHttpResponseCode($httpResponseCode);
        $this->response->setBody($this->serializer->serialize($responseBody));
        $this->response->sendResponse();
    }
}
