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

use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface as ExternalCustomerEntityRepository;
use Bolt\Boltpay\Api\OAuthRedirectInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Model\StoreManagerInterface;

class OAuthRedirect implements OAuthRedirectInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var DeciderHelper
     */
    private $deciderHelper;

    /**
     * @var SSOHelper
     */
    private $ssoHelper;

    /**
     * @var ExternalCustomerEntityRepository
     */
    private $externalCustomerEntityRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerInterfaceFactory;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Response                         $response
     * @param DeciderHelper                    $deciderHelper
     * @param SSOHelper                        $ssoHelper
     * @param ExternalCustomerEntityRepository $externalCustomerEntityRepository
     * @param CustomerRepository               $customerRepository
     * @param StoreManagerInterface            $storeManager
     * @param CustomerSession                  $customerSession
     * @param CustomerInterfaceFactory         $customerInterfaceFactory
     * @param CustomerFactory                  $customerFactory
     * @param Url                              $url
     * @param Bugsnag                          $bugsnag
     */
    public function __construct(
        Response $response,
        DeciderHelper $deciderHelper,
        SSOHelper $ssoHelper,
        ExternalCustomerEntityRepository $externalCustomerEntityRepository,
        CustomerRepository $customerRepository,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CustomerInterfaceFactory $customerInterfaceFactory,
        CustomerFactory $customerFactory,
        Url $url,
        Bugsnag $bugsnag
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
        $this->ssoHelper = $ssoHelper;
        $this->externalCustomerEntityRepository = $externalCustomerEntityRepository;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->customerFactory = $customerFactory;
        $this->url = $url;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Login with Bolt SSO and redirect
     *
     * @api
     *
     * @param string $code
     * @param string $scope
     * @param string $state
     *
     * @return void
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function login($code = '', $scope = '', $state = '')
    {
        if (!$this->deciderHelper->isBoltSSOEnabled()) {
            throw new NoSuchEntityException(__('Request does not match any route.'));
        }

        if ($code === '' || $scope === '' || $state === '') {
            throw new WebapiException(__('Bad Request'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        list($clientID, $clientSecret, $boltPublicKey) = $this->ssoHelper->getOAuthConfiguration();

        $token = $this->ssoHelper->exchangeToken($code, $scope, $clientID, $clientSecret);
        if ($token === null) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        $payload = $this->ssoHelper->parseAndValidateJWT($token, $clientID, $boltPublicKey);
        if ($payload === null) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        $externalID = $payload['sub'];

        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $storeId = $this->storeManager->getStore()->getId();

        $externalCustomerEntity = null;
        $customer = null;
        try {
            $externalCustomerEntity = $this->externalCustomerEntityRepository->getByExternalID($externalID);
        } catch (NoSuchEntityException $nsee) {
            // external customer entity not found
        }
        try {
            if ($externalCustomerEntity !== null) {
                $customer = $this->customerRepository->getById($externalCustomerEntity->getCustomerID());
            } else {
                $customer = $this->customerRepository->get($payload['email'], $websiteId);
            }
        } catch (NoSuchEntityException $nsee) {
            // customer not found
        }

        // External customer entity exists, but the customer it links to doesn't
        // This should happen very rarely, if at all, which is why we notify bugsnag when this happens
        if ($externalCustomerEntity !== null && $customer === null) {
            $this->bugsnag->notifyError(
                'OAuthRedirect',
                'external customer entity ' . $externalID . ' linked to nonexistent customer ' . $externalCustomerEntity->getCustomerID()
            );
        }

        // If
        // - external customer entity isn't linked
        // - customer exists in M2
        // - email is not verified
        // Notify bugsnag and throw an exception
        if ($externalCustomerEntity === null && $customer !== null && !$payload['email_verified']) {
            $this->bugsnag->notifyError(
                'OAuthRedirect',
                'customer with email ' . $payload['email'] . ' found but email is not verified'
            );
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        try {
            $customer = $customer ?: $this->createNewCustomer($websiteId, $storeId, $payload);
            $this->linkLoginAndRedirect($externalID, $customer->getId());
        } catch (Exception $e) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Create new customer from provided parameters
     *
     * @param int   $websiteId
     * @param int   $storeId
     * @param mixed $payload
     */
    private function createNewCustomer($websiteId, $storeId, $payload)
    {
        $newCustomer = $this->customerInterfaceFactory->create();
        $newCustomer->setWebsiteId($websiteId);
        $newCustomer->setStoreId($storeId);
        $newCustomer->setFirstname($payload['first_name']);
        $newCustomer->setLastname($payload['last_name']);
        $newCustomer->setEmail($payload['email']);
        $newCustomer->setConfirmation(null);
        return $this->customerRepository->save($newCustomer);
    }

    /**
     * Link the external ID to customer if needed, log in, and redirect
     *
     * @param string $externalID
     * @param int    $customerID
     */
    private function linkLoginAndRedirect($externalID, $customerID)
    {
        $this->externalCustomerEntityRepository->upsert($externalID, $customerID);
        $customerModel = $this->customerFactory->create()->load($customerID);
        $this->customerSession->setCustomerAsLoggedIn($customerModel);
        $this->response->setRedirect($this->url->getAccountUrl())->sendResponse();
    }
}
