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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\GetAccountInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;

class GetAccount implements GetAccountInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Response              $response
     * @param CustomerRepository    $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param HookHelper            $hookHelper
     * @param Bugsnag               $bugsnag
     */
    public function __construct(
        Response $response,
        CustomerRepository $customerRepository,
        StoreManagerInterface $storeManager,
        HookHelper $hookHelper,
        Bugsnag $bugsnag
    ) {
        $this->response = $response;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->hookHelper = $hookHelper;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Get user account associated with email
     *
     * @api
     *
     * @param string $email
     *
     * @return void
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function execute($email = '')
    {
        if (!$this->hookHelper->verifyRequest()) {
            throw new WebapiException(__('Request is not authenticated.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }

        if ($email === '') {
            throw new WebapiException(__('Missing email in the request body.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        try {
            $websiteId = $this->storeManager->getStore()->getWebsiteId();
            $customer = $this->customerRepository->get($email, $websiteId);
            $this->response->setHeader('Content-Type', 'application/json');
            $this->response->setHttpResponseCode(200);
            $this->response->setBody(json_encode(['id' => $customer->getId()]));
            $this->response->sendResponse();
        } catch (NoSuchEntityException $nsee) {
            throw new NoSuchEntityException(__('Customer not found with given email.'));
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}
