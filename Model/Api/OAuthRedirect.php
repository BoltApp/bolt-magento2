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

use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface;
use Bolt\Boltpay\Api\OAuthRedirectInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
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
     * @var ExternalCustomerEntityRepositoryInterface
     */
    private $externalCustomerEntityRepositoryInterface;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepositoryInterface;

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
     * @param Response                                  $response
     * @param DeciderHelper                             $deciderHelper
     * @param SSOHelper                                 $ssoHelper
     * @param ExternalCustomerEntityRepositoryInterface $externalCustomerEntityRepositoryInterface
     * @param CustomerRepositoryInterface               $customerRepositoryInterface
     * @param StoreManagerInterface                     $storeManager
     * @param CustomerSession                           $customerSession
     * @param CustomerInterfaceFactory                  $customerInterfaceFactory
     */
    public function __construct(
        Response $response,
        DeciderHelper $deciderHelper,
        SSOHelper $ssoHelper,
        ExternalCustomerEntityRepositoryInterface $externalCustomerEntityRepositoryInterface,
        CustomerRepositoryInterface $customerRepositoryInterface,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CustomerInterfaceFactory $customerInterfaceFactory
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
        $this->ssoHelper = $ssoHelper;
        $this->externalCustomerEntityRepositoryInterface = $externalCustomerEntityRepositoryInterface;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
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
            $externalCustomerEntity = $this->externalCustomerEntityRepositoryInterface->getByExternalID($payload['sub']);
        } catch (NoSuchEntityException $nsee) {
        }
        try {
            $customer = $this->customerRepositoryInterface->get($payload['email'], $websiteId);
        } catch (NoSuchEntityException $nsee) {
        }

        if ($externalCustomerEntity !== null) {
            if ($customer === null) {
                throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
            }

            $this->customerSession->setCustomerAsLoggedIn($customer);
            // redirect
        }

        if ($customer !== null && !$payload['email_verified']) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        try {
            $customer = $this->customerInterfaceFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->setStoreId($storeId);
            $customer->setFirstname($payload['first_name']);
            $customer->setLastname($payload['last_name']);
            $customer->setEmail($payload['email']);
            $customer->setConfirmation(null);
            $customer = $this->customerRepository->save($customer);

            $this->customerSession->setCustomerAsLoggedIn($customer);
            // redirect
        } catch (Exception $e) {
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}
