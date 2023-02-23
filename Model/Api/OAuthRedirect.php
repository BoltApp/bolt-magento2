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
 *
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\ExternalCustomerEntityRepositoryInterface as ExternalCustomerEntityRepository;
use Bolt\Boltpay\Api\OAuthRedirectInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Bolt\Boltpay\Helper\SSOHelper;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\EmailNotification;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Session\Config\ConfigInterface as SessionConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\PageCache\FormKey as CookieFormKey;
use Magento\Framework\Exception\LocalizedException;

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
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var SessionHelper
    */
    private $sessionHelper;

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
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var EmailNotification
     */
    private $emailNotification;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    private $sessionConfig;

    private $formKey;

    private $cookieFormKey;

    /**
     * @param Response $response
     * @param DeciderHelper $deciderHelper
     * @param SSOHelper $ssoHelper
     * @param LogHelper $logHelper
     * @param CartHelper $cartHelper
     * @param SessionHelper $sessionHelper
     * @param ExternalCustomerEntityRepository $externalCustomerEntityRepository
     * @param CustomerRepository $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param CustomerInterfaceFactory $customerInterfaceFactory
     * @param CustomerFactory $customerFactory
     * @param OrderRepository $orderRepository
     * @param ResourceConnection $resourceConnection
     * @param QuoteFactory $quoteFactory
     * @param Url $url
     * @param Bugsnag $bugsnag
     * @param EmailNotification $emailNotification
     * @param CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        Response $response,
        DeciderHelper $deciderHelper,
        SSOHelper $ssoHelper,
        LogHelper $logHelper,
        CartHelper $cartHelper,
        SessionHelper $sessionHelper,
        ExternalCustomerEntityRepository $externalCustomerEntityRepository,
        CustomerRepository $customerRepository,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CustomerInterfaceFactory $customerInterfaceFactory,
        CustomerFactory $customerFactory,
        OrderRepository $orderRepository,
        ResourceConnection $resourceConnection,
        QuoteFactory $quoteFactory,
        Url $url,
        Bugsnag $bugsnag,
        EmailNotification $emailNotification,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        SessionConfigInterface $sessionConfig,
        FormKey $formKey,
        CookieFormKey $cookieFormKey
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
        $this->ssoHelper = $ssoHelper;
        $this->logHelper = $logHelper;
        $this->cartHelper = $cartHelper;
        $this->sessionHelper = $sessionHelper;
        $this->externalCustomerEntityRepository = $externalCustomerEntityRepository;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->customerFactory = $customerFactory;
        $this->orderRepository = $orderRepository;
        $this->resourceConnection = $resourceConnection;
        $this->quoteFactory = $quoteFactory;
        $this->url = $url;
        $this->bugsnag = $bugsnag;
        $this->emailNotification = $emailNotification;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->sessionConfig = $sessionConfig;
        $this->formKey = $formKey;
        $this->cookieFormKey = $cookieFormKey;
    }

    /**
     * Retrieve cookie manager
     *
     * @return \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\PhpCookieManager::class
            );
        }
        return $this->cookieMetadataManager;
    }

    /**
     * Retrieve cookie metadata factory
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }
    /**
     * Login with Bolt SSO and redirect
     *
     * @api
     *
     * @param string $code
     * @param string $scope
     * @param string $state
     * @param string $reference
     *
     * @return void
     *
     * @throws NoSuchEntityException
     * @throws WebapiException
     */
    public function login($code = '', $scope = '', $state = '', $reference = '')
    {
        Hook::$fromBolt = true;
        if (!$this->deciderHelper->isBoltSSOEnabled()) {
            $this->bugsnag->notifyError('OAuthRedirect', 'BoltSSO feature is disabled');
            throw new NoSuchEntityException(__('Request does not match any route.'));
        }

        if ($code === '' || $scope === '' || $state === '') {
            $this->bugsnag->notifyError('OAuthRedirect', 'Bad Request');
            throw new WebapiException(__('Bad Request'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        list($clientID, $clientSecret, $boltPublicKey) = $this->ssoHelper->getOAuthConfiguration();

        $token = $this->ssoHelper->exchangeToken($code, $scope, $clientID, $clientSecret);
        if (is_string($token)) {
            $this->bugsnag->notifyError('OAuthRedirect', $token);
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }

        $payload = $this->ssoHelper->parseAndValidateJWT($token->{'id_token'}, $clientID, $boltPublicKey);
        if (is_string($payload)) {
            $this->bugsnag->notifyError('OAuthRedirect', $payload);
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
            if (!$customer) {
                $customer = $this->createNewCustomer($websiteId, $storeId, $payload);
                // Send confirmation email
                try {
                    if ($customer->getId()) {
                        $this->emailNotification->newAccount($customer, \Magento\Customer\Model\EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD,'', $customer->getStoreId());
                    }
                } catch (\Exception $exception) {
                    $this->bugsnag->notifyException($exception);
                }
            }

            // The reference parameter is actually the Bolt Parent Quote ID
            if ($reference !== '') {
                $order = $this->cartHelper->getOrderByQuoteId($reference);
                if ($order !== false) {
                    $order->setCustomerId($customer->getId());
                    $order->setCustomerFirstname($customer->getFirstname());
                    $order->setCustomerMiddlename($customer->getMiddlename());
                    $order->setCustomerLastname($customer->getLastname());
                    $order->setCustomerGroupId($customer->getGroupId());
                    $order->setCustomerIsGuest(0);
                    $this->orderRepository->save($order);
                }

                // The checkout may not have been completed yet, but the user may have logged in via Bolt SSO
                $quote = $this->cartHelper->getQuoteById($reference);
                if ($quote !== false) {
                    try {
                        // we should make current customer cart as inactive and use guest cart as customer cart if login from bolt modal
                        $customerActiveQuote = $this->cartRepository->getActiveForCustomer($customer->getId());
                        $customerActiveQuote->setIsActive(false);
                        $this->cartHelper->saveQuote($customerActiveQuote);
                    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                        $this->bugsnag->notifyException($e);
                    }
                    $quote->setCustomer($customer);
                    $quote->setCustomerIsGuest(false);
                    $this->cartHelper->saveQuote($quote);
                    $this->updateImmutableQuotes($quote, $customer);
                } else {
                    $this->bugsnag->notifyError("Cannot find quote", "ID: {$reference}");
                }
            } else {
                // user logs in outside of bolt modal but we still need to update quote
                // if it exists
                $checkoutSession = $this->sessionHelper->getCheckoutSession();
                if ($checkoutSession) {
                    $quote = $checkoutSession->getQuote();
                    if ($quote->getId()) {
                        try {
                            // we should merge quote here for magento 2.3.X version and below, because
                            // https://github.com/magento/magento2/blob/44a7b6079bcac5ba92040b16f4f74024b4f34d09/app/code/Magento/Quote/Model/QuoteManagement.php#L297
                            // doesn't have "quote merging part" as we have on magento 2.4.X
                            // https://github.com/magento/magento2/blob/5844ade68b2f9632e3888c81c946068eba6328bb/app/code/Magento/Quote/Model/QuoteManagement.php#L337
                            $customerActiveQuote = $this->cartRepository->getActiveForCustomer($customer->getId());
                            $quote->merge($customerActiveQuote);
                            $customerActiveQuote->setIsActive(false);
                            $this->cartHelper->saveQuote($customerActiveQuote);
                        } catch (NoSuchEntityException $e) {
                            $this->bugsnag->notifyException($e);
                        }
                        // call the function that merge 2 carts: guest cart and customer cart
                        $this->cartManagement->assignCustomer($quote->getId(),$customer->getId(),$customer->getStoreId());
                        $this->updateImmutableQuotes($quote, $customer);
                    }
                }
            }

            $this->linkLoginAndRedirect($externalID, $customer->getId());
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__('Internal Server Error'), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Update all immutable (child) quotes with logged-in customer
     *
     * @param Quote               $parentQuote
     * @param CustomerInterface   $customer
     */
    private function updateImmutableQuotes($parentQuote, $customer)
    {
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['c' => $quoteTable],
                ['*']
            )
            ->where('c.bolt_parent_quote_id = :bolt_parent_quote_id')
            ->where('c.entity_id != :entity_id');
        $bind = [
            'bolt_parent_quote_id' => $parentQuote->getId(),
            'entity_id' => $parentQuote->getId()
        ];

        $results = $connection->fetchAll($select, $bind);
        foreach ($results as $data) {
            $immutableQuote = $this->quoteFactory->create();
            $immutableQuote->setData($data);
            $immutableQuote->setCustomer($customer);
            $immutableQuote->setCustomerIsGuest(false);
            // Magento quote repository save() method triggers collectTotals() method for quote.
            // Which is triggering some third-party tax extensions to request & collect taxes.
            // In order to avoid repeatable tax requests from third-party extensions
            // we use resource model to save quote instead of repositoryFix
            $immutableQuote->save();
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
        $customer = $this->customerRepository->getById($customerID);
        $this->customerSession->setCustomerDataAsLoggedIn($customer);

        if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
            $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
            $metadata->setPath('/');
            $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
        }

        try {
            // we should force to set new "form_key" cookie here to prevent safari "session expired" issue
            // when we are logged-in by SSO without page redirect (by using Bolt Shopper Assistant for example)
            // in this case magento removing cookie by backend here:
            // https://github.com/magento/magento2/blob/5844ade68b2f9632e3888c81c946068eba6328bb/app/code/Magento/PageCache/Observer/FlushFormKey.php
            // but on safari without redirect the cookie is removed from browser (you can't see cookie on the devtool) but not in window.document object
            // (we can see the cookie "form_key" there by checking object in browser console: "document.cookie")
            // therefore all future requests is being called without "form_key" which is the reason of  "session expire" issue.
            // By normal flow Magento updates that cookie in file:
            // 2.2.0 - 2.3.0 https://github.com/magento/magento2/blob/44a7b6079bcac5ba92040b16f4f74024b4f34d09/app/code/Magento/PageCache/view/frontend/web/js/page-cache.js#L115
            // 2.4.0 https://github.com/magento/magento2/blob/5844ade68b2f9632e3888c81c946068eba6328bb/app/code/Magento/PageCache/view/frontend/web/js/form-key-provider.js#L87
            // Magento js above sets new cookie only in case if cookie is not exist
            // but in our issue case magento js file is able to read cookie from document object (because cookie was not removed by safari)
            // and doesn't update browser cookie.
            $this->updateFormKeyCookie();
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
        $this->response->setRedirect($this->url->getAccountUrl())->sendResponse();

    }

    /**
     * Set form key cookie
     *
     * @return void
     * @throws LocalizedException
     */
    private function updateFormKeyCookie()
    {
        $cookieMetadata = $this->getCookieMetadataFactory()->createPublicCookieMetadata();
        $cookieMetadata->setDomain($this->sessionConfig->getCookieDomain());
        $cookieMetadata->setPath($this->sessionConfig->getCookiePath());
        $cookieMetadata->setSecure($this->sessionConfig->getCookieSecure());
        $lifetime = $this->sessionConfig->getCookieLifetime();
        if ($lifetime !== 0) {
            $cookieMetadata->setDuration($lifetime);
        }
        $this->cookieFormKey->set(
            $this->formKey->getFormKey(),
            $cookieMetadata
        );
    }
}
