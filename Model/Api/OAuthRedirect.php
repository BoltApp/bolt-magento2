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
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
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
     * @var Url
     */
    private $url;

    /**
     * @param Response                         $response
     * @param DeciderHelper                    $deciderHelper
     * @param SSOHelper                        $ssoHelper
     * @param ExternalCustomerEntityRepository $externalCustomerEntityRepository
     * @param CustomerRepository               $customerRepository
     * @param StoreManagerInterface            $storeManager
     * @param CustomerSession                  $customerSession
     * @param CustomerInterfaceFactory         $customerInterfaceFactory
     * @param Url                              $url
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
        Url $url
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
        $this->ssoHelper = $ssoHelper;
        $this->externalCustomerEntityRepository = $externalCustomerEntityRepository;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->url = $url;
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

        $oauthConfiguration = $this->ssoHelper->getOAuthConfiguration();
        $clientID = $oauthConfiguration['clientID'];
        $clientSecret = $oauthConfiguration['clientSecret'];
        $boltPublicKey = $oauthConfiguration['boltPublicKey'];

        $token = $this->ssoHelper->exchangeToken($code, $scope, $clientID, $clientSecret);
        if ($token === null) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        $payload = $this->ssoHelper->parseAndValidateJWT($token, $clientID, $boltPublicKey);
        if ($payload === null) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $storeId = $this->storeManager->getStore()->getId();

        $externalCustomerEntity = null;
        $customer = null;
        try {
            $externalCustomerEntity = $this->externalCustomerEntityRepository->getByExternalID($payload['sub']);
        } catch (NoSuchEntityException $nsee) {
        }
        try {
            $customer = $this->customerRepository->get($payload['email'], $websiteId);
        } catch (NoSuchEntityException $nsee) {
        }

        if ($externalCustomerEntity !== null) {
            if ($customer === null) {
                throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
            }

            $this->linkAndLogin($payload['sub'], $customer);
            return;
        }

        if ($customer !== null) {
            if (!$payload['email_verified']) {
                throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
            }

            $this->linkAndLogin($payload['sub'], $customer);
            return;
        }

        try {
            $newCustomer = $this->customerInterfaceFactory->create();
            $newCustomer->setWebsiteId($websiteId);
            $newCustomer->setStoreId($storeId);
            $newCustomer->setFirstname($payload['first_name']);
            $newCustomer->setLastname($payload['last_name']);
            $newCustomer->setEmail($payload['email']);
            $newCustomer->setConfirmation(null);
            $customer = $this->customerRepository->save($newCustomer);
            $this->linkAndLogin($payload['sub'], $customer);
        } catch (Exception $e) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Link the external ID to customer, log in, and redirect
     *
     * @param string            $externalID
     * @param CustomerInterface $customer
     */
    private function linkAndLogin($externalID, $customer)
    {
        $this->externalCustomerEntityRepository->create($externalID, $customer->getId());
        $this->customerSession->setCustomerAsLoggedIn($customer);
        $this->response->setRedirect($this->url->getAccountUrl())->sendResponse();
    }
}
