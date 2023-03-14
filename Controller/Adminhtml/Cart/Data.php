<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category  Bolt
 * @Package   Bolt_Boltpay
 * @copyright Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Controller\Adminhtml\Cart;

use Bolt\Boltpay\Helper\Cart as CartHelper;
use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Catalog\Helper\Product;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\DataObjectFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

/**
 * Class Data.
 * Create Bolt order controller.
 *
 * Called from the replace.phtml javascript block on checkout button click.
 */
class Data extends \Magento\Sales\Controller\Adminhtml\Order\Create
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var DataObjectFactory
     *
     * @deprecated
     */
    private $dataObjectFactory;

    /**
     * @var StoreManagerInterface|null
     */
    private $storeManager;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * Core registry
     *
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var Decider
     */
    private $decider;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CartHelper $cartHelper
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
     * @param MetricsClient $metricsClient
     * @param DataObjectFactory $dataObjectFactory
     * @param Registry $coreRegistry
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     * @param Product|null $productHelper
     * @param Escaper|null $escaper
     * @param PageFactory|null $resultPageFactory
     * @param ForwardFactory|null $resultForwardFactory
     * @param StoreManagerInterface|null $storeManager
     * @param Decider $decider
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CartHelper $cartHelper,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        MetricsClient $metricsClient,
        DataObjectFactory $dataObjectFactory,
        Registry $coreRegistry,
        EventsForThirdPartyModules $eventsForThirdPartyModules,
        Product $productHelper = null,
        Escaper $escaper = null,
        PageFactory $resultPageFactory = null,
        ForwardFactory $resultForwardFactory = null,
        StoreManagerInterface $storeManager = null,
        ?Decider $decider = null,
    ) {
        parent::__construct(
            $context,
            $productHelper ?: ObjectManager::getInstance()->get(Product::class),
            $escaper ?: ObjectManager::getInstance()->get(Escaper::class),
            $resultPageFactory ?: ObjectManager::getInstance()->get(PageFactory::class),
            $resultForwardFactory ?: ObjectManager::getInstance()->get(ForwardFactory::class)
        );
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartHelper = $cartHelper;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->metricsClient = $metricsClient;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->coreRegistry = $coreRegistry;
        $this->storeManager = $storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
        $this->decider = $decider ?: ObjectManager::getInstance()->get(Decider::class);
    }

    /**
     * Get cart data for bolt pay ajax
     *
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        $startTime = $this->metricsClient->getCurrentTime();
        try {
            $storeId = $this->_getSession()->getStoreId();
            if (!$storeId) {
                throw new LocalizedException(__('Order creation not initialized'));
            }
            $this->_initSession();

            $quote = $this->_getOrderCreateModel()->getQuote();
            $this->storeManager->setCurrentStore($storeId);

            $backOfficeKey = $this->configHelper->getPublishableKeyBackOffice($storeId);
            $paymentOnlyKey = $this->configHelper->getPublishableKeyPayment($storeId);
            $isPreAuth = $this->configHelper->getIsPreAuth($storeId);
            $connectUrl = $this->configHelper->getCdnUrl($storeId) . '/connect.js';

            if ($this->decider->isEnabledFetchCartViaApi()) {
                // set bolt checkout type to backoffice as a flag for forming correct success redirect url
                if (!$quote->getBoltCheckoutType()) {
                    $quote->setBoltCheckoutType(CartHelper::BOLT_CHECKOUT_TYPE_BACKOFFICE);
                    $this->cartHelper->quoteResourceSave($quote);
                }
                $hints = $this->cartHelper->getHints();
                return $this->resultJsonFactory->create()
                    ->setData(
                        [
                            'cart'           => ['id' => $this->cartHelper->getQuoteMaskedId((int)$quote->getId())],
                            'hints'          => $hints,
                            'backOfficeKey'  => $backOfficeKey,
                            'paymentOnlyKey' => $paymentOnlyKey,
                            'storeId'        => $storeId,
                            'isPreAuth'      => $isPreAuth,
                            'connectUrl'     => $connectUrl
                        ]
                    );
            }
            /**
             * initialize rule data for backend orders, consumed by
             * @see \Magento\CatalogRule\Observer\ProcessAdminFinalPriceObserver::execute
             */
            $this->coreRegistry->unregister('rule_data');
            $this->coreRegistry->register(
                'rule_data',
                new \Magento\Framework\DataObject(
                    [
                        'store_id' => $storeId,
                        'website_id' => $this->_getSession()->getStore()->getWebsiteId(),
                        'customer_group_id' => $quote->getCustomerGroupId()
                    ]
               )
            );

            $customerEmail = $quote->getCustomerEmail();
            if (!$quote->getCustomerId()) {
                if ($this->cartHelper->getCustomerByEmail($customerEmail)) {
                    throw new LocalizedException(
                        __('A customer with the same email address already exists in an associated website.')
                    );
                }
                $this->_getOrderCreateModel()->getBillingAddress()->setEmail($customerEmail);
                $quote->setCustomerEmail($customerEmail);
                $this->_getOrderCreateModel()->saveQuote();
            }

            $this->eventsForThirdPartyModules->dispatchEvent("beforeGetBoltpayOrderForBackofficeOrder", $this);
            // call the Bolt API
            $boltpayOrder = $this->cartHelper->getBoltpayOrder(true);
            // If empty cart - order_token not fetched because doesn't exist. Not a failure.
            if ($boltpayOrder) {
                $this->metricsClient->processMetric(
                    "back_office_order_token.success",
                    1,
                    "back_office_order_token.latency",
                    $startTime
                );
            }

            // format and send the response
            $hints = $this->cartHelper->getHints();

            if (!$boltpayOrder || !$boltpayOrder->getResponse() || !$boltpayOrder->getResponse()->token) {
                throw new LocalizedException(
                    __('Bolt order was not created successfully')
                );
            }
            return $this->resultJsonFactory->create()
                ->setData(
                    [
                        'cart'           => ['orderToken' => $boltpayOrder->getResponse()->token],
                        'hints'          => $hints,
                        'backOfficeKey'  => $backOfficeKey,
                        'paymentOnlyKey' => $paymentOnlyKey,
                        'storeId'        => $storeId,
                        'isPreAuth'      => $isPreAuth,
                        'connectUrl'     => $connectUrl
                    ]
                );
        } catch (LocalizedException $e) {
            return $this->resultJsonFactory->create()->setData(
                [
                    'cart'           => ['errorMessage' => $e->getMessage()],
                    'hints'          => $hints ?? [],
                    'backOfficeKey'  => $backOfficeKey ?? '',
                    'paymentOnlyKey' => $paymentOnlyKey ?? '',
                    'storeId'        => $storeId ?? '',
                    'isPreAuth'      => $isPreAuth ?? '',
                    'connectUrl'     => $connectUrl ?? '',
                ]
            );
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            $this->metricsClient->processMetric(
                "back_office_order_token.failure",
                1,
                "back_office_order_token.latency",
                $startTime
            );
            throw $e;
        }
    }
}
