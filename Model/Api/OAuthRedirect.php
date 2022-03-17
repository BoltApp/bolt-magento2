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
use Bolt\Boltpay\Helper\Log as LogHelper;
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
     * @param Response                         $response
     * @param DeciderHelper                    $deciderHelper
     * @param SSOHelper                        $ssoHelper
     * @param LogHelper                        $logHelper
     * @param CartHelper                       $cartHelper
     * @param ExternalCustomerEntityRepository $externalCustomerEntityRepository
     * @param CustomerRepository               $customerRepository
     * @param StoreManagerInterface            $storeManager
     * @param CustomerSession                  $customerSession
     * @param CustomerInterfaceFactory         $customerInterfaceFactory
     * @param CustomerFactory                  $customerFactory
     * @param OrderRepository                  $orderRepository
     * @param ResourceConnection               $resourceConnection
     * @param QuoteFactory                     $quoteFactory
     * @param Url                              $url
     * @param Bugsnag                          $bugsnag
     * @param EmailNotification                $emailNotification
     */
    public function __construct(
        Response $response,
        DeciderHelper $deciderHelper,
        SSOHelper $ssoHelper,
        LogHelper $logHelper,
        CartHelper $cartHelper,
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
        EmailNotification $emailNotification
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
        $this->ssoHelper = $ssoHelper;
        $this->logHelper = $logHelper;
        $this->cartHelper = $cartHelper;
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
    }

    /**
     * Retrieve cookie manager
     *
     * @deprecated 100.1.0
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
                    $quote->setCustomer($customer);
                    $quote->setCustomerIsGuest(false);
                    $this->cartHelper->saveQuote($quote);

                    $this->updateImmutableQuotes($quote, $customer);
                } else {
                    $this->bugsnag->notifyError("Cannot find quote", "ID: {$reference}");
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
            $this->cartHelper->saveQuote($immutableQuote);
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

        if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
            $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
            $metadata->setPath('/');
            $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
        }

        $this->response->setRedirect($this->url->getAccountUrl())->sendResponse();

    }
}
