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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use \Magento\Framework\App\State as AppState;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\RuleRepository;
use Bolt\Boltpay\Model\EventsForThirdPartyModules;

/**
 * Boltpay Discount helper class
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Discount extends AbstractHelper
{
    // Discount totals key identifiers
    const AMASTY_GIFTCARD = 'amasty_giftcard';
    const GIFT_VOUCHER_AFTER_TAX = 'giftvoucheraftertax';
    const GIFT_VOUCHER = 'giftvoucher';
    const GIFT_CARD_ACCOUNT = 'giftcardaccount';
    const UNIRGY_GIFT_CERT = 'ugiftcert';
    const MAGEPLAZA_GIFTCARD = 'gift_card';
    const MAGEPLAZA_GIFTCARD_QUOTE_KEY = 'mp_gift_cards';
    const AHEADWORKS_STORE_CREDIT = 'aw_store_credit';
    
    // Bolt discount category
    const BOLT_DISCOUNT_CATEGORY_STORE_CREDIT = 'store_credit';
    const BOLT_DISCOUNT_CATEGORY_COUPON = 'coupon';
    const BOLT_DISCOUNT_CATEGORY_GIFTCARD = 'giftcard';
    const BOLT_DISCOUNT_CATEGORY_AUTO_PROMO = 'automatic_promotion';
    
    // In Magento 2, 0.005 would be converted into 0.01 which is greater than 0 while 0.0049999 would be converted into 0,
    // so we can treat 0.005 as the closest number to zero in M2.
    const MIN_NONZERO_VALUE = 0.005;

    /**
     * @var ResourceConnection $resource
     */
    private $resource;

    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Model\GiftcertRepository
     */
    protected $unirgyCertRepository;
    
    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Helper\Data
     */
    protected $unirgyGiftCertHelper;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var AppState
     */
    private $appState;

    /**
     * @var Session
     */
    private $sessionHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;
    
    /**
     * @var CouponFactory
     */
    protected $couponFactory;
    
    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * Discount constructor.
     * @param Context $context
     * @param ResourceConnection $resource
     * @param ThirdPartyModuleFactory $unirgyCertRepository
     * @param ThirdPartyModuleFactory $unirgyGiftCertHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param Config $configHelper
     * @param Bugsnag $bugsnag
     * @param AppState $appState
     * @param Session $sessionHelper
     * @param Log $logHelper
     * @param CouponFactory $couponFactory
     * @param RuleRepository $ruleRepository
     * @param EventsForThirdPartyModules $eventsForThirdPartyModules
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        ThirdPartyModuleFactory $unirgyCertRepository,
        ThirdPartyModuleFactory $unirgyGiftCertHelper,
        CartRepositoryInterface $quoteRepository,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        AppState $appState,
        Session $sessionHelper,
        LogHelper $logHelper,
        CouponFactory $couponFactory,
        RuleRepository $ruleRepository,
        EventsForThirdPartyModules $eventsForThirdPartyModules
    ) {
        parent::__construct($context);
        $this->resource = $resource;
        $this->unirgyCertRepository = $unirgyCertRepository;
        $this->unirgyGiftCertHelper = $unirgyGiftCertHelper;
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->appState = $appState;
        $this->sessionHelper = $sessionHelper;
        $this->logHelper = $logHelper;
        $this->couponFactory = $couponFactory;
        $this->ruleRepository = $ruleRepository;
        $this->eventsForThirdPartyModules = $eventsForThirdPartyModules;
    }
    
    /**
     * Collect and update quote totals.
     * @param Quote $quote
     */
    private function updateTotals(Quote $quote)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->setDataChanges(true);
        $this->quoteRepository->save($quote);
    }

    /**
     * Get Amasty "Pay for everything" (items, shipping and tax) additional configuration property.
     * True by default if configuration is not explicitly set to false.
     * The plugin currently supports both Amasty configurations,
     * "Use a gift card to pay for shipping" and "Use a gift card to pay for tax" set to either "Yes" or "No".
     * Any "Yes" + "No" combination is not supported by Bolt.
     *
     * @return bool
     */
    public function getAmastyPayForEverything()
    {
        $amastyGiftCardConfig = $this->configHelper->getAmastyGiftCardConfig();
        if ($amastyGiftCardConfig && empty($amastyGiftCardConfig->payForEverything)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $code
     * @param string|int $storeId
     *
     * @return null|\Unirgy\Giftcert\Model\Cert
     * @throws NoSuchEntityException
     */
    public function loadUnirgyGiftCertData($code, $storeId)
    {
        $result = null;

        /** @var \Unirgy\Giftcert\Model\GiftcertRepository $giftCertRepository */
        $giftCertRepository = $this->unirgyCertRepository->getInstance();

        if ($giftCertRepository) {
            $this->logHelper->addInfoLog('### GiftCert ###');
            $this->logHelper->addInfoLog('# Code: ' . $code);

            try {
                /** @var \Unirgy\Giftcert\Model\Cert $giftCert */
                $giftCert = $giftCertRepository->get($code);

                $gcStoreId = $giftCert->getStoreId();

                $result = ((!$gcStoreId || $gcStoreId == $storeId) && $giftCert->getData('status') === 'A')
                          ? $giftCert : null;

            } catch (NoSuchEntityException $e) {
                //We must ignore the exception, because it is thrown when data does not exist.
                $result = null;
            }
        }

        $this->logHelper->addInfoLog('# loadUnirgyGiftCertData Result is empty: ' . ((!$result) ? 'yes' : 'no'));

        return $result;
    }
    
    /**
     * Apply Unirgy Gift Cert to quote
     *
     * @param Quote $quote
     * @param string $giftCard
     * 
     */
    public function addUnirgyGiftCertToQuote($quote, $giftCard)
    {
        $unirgyHelper = $this->unirgyGiftCertHelper->getInstance();
        
        if ($unirgyHelper) {
            if (empty($quote->getData($giftCard::GIFTCERT_CODE))) {
                $unirgyHelper->addCertificate(
                    $giftCard->getCertNumber(),
                    $quote,
                    $this->quoteRepository
                );
            }
        }
    }


    /**
     * Get Unirgy_Giftcert balance.
     *
     * @param string $giftcertCode
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getUnirgyGiftCertBalanceByCode($giftcertCode)
    {
        $result = 0;

        /** @var \Unirgy\Giftcert\Model\Cert $giftCert */
        $unirgyInstance = $this->unirgyCertRepository->getInstance();

        if ($unirgyInstance) {

            $giftCodes = array_map('trim', explode(',', $giftcertCode));

            foreach ($giftCodes as $giftCode) {
                $giftCert = $unirgyInstance->get($giftCode);
                if ($giftCert && $giftCert->getStatus() === 'A' && $giftCert->getBalance() > 0) {
                    $result += $giftCert->getBalance();
                }
            }
        }

        return (float) $result;
    }

    /**
     * Some 3rd party discounts are not stored with the quote / totals. They are held in other tables
     * and applied to quote temporarily in checkout / customer session via magic set methods.
     * There is need in API calls (shipping & tax and webhook) to explicitly fetch and set this data
     * to have quote object in sync with the one used in session.
     *
     * @param Quote $quote
     */
    public function applyExternalDiscountData($quote)
    {
        $this->eventsForThirdPartyModules->dispatchEvent("applyExternalDiscountData", $quote);
    }
    
    /**
     * Load the coupon data by code
     *
     * @param $couponCode
     *
     * @return Coupon
     */
    public function loadCouponCodeData($couponCode)
    {
        return $this->couponFactory->create()->loadByCode($couponCode);
    }
    
    /**
     * @param string $couponCode
     * @return string
     */
    public function convertToBoltDiscountType($couponCode)
    {
        if ($couponCode == "") {
            return "fixed_amount";
        }
        
        try {
            $coupon = $this->loadCouponCodeData($couponCode);
            // Load the coupon discount rule
            $rule = $this->ruleRepository->getById($coupon->getRuleId());        
            $type = $rule->getSimpleAction();
            
            return $this->getBoltDiscountType($type);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        }        
    }
    
    /**
     * @param string $type
     * @return string
     */
    public function getBoltDiscountType($type)
    {
        switch ($type) {
            case "by_fixed":
            case "cart_fixed":
                return "fixed_amount";
            case "by_percent":
                return "percentage";
            case "by_shipping":
                return "shipping";
        }

        return "fixed_amount";
    }
    
    /**
     * Set applied coupon code
     *
     * @param Quote  $quote
     * @param string $couponCode
     * @throws \Exception
     */
    public function setCouponCode($quote, $couponCode)
    {
        $address = $quote->isVirtual() ?
                $quote->getBillingAddress() :
                $quote->getShippingAddress();
        $address->setAppliedRuleIds('');
        $quote->setAppliedRuleIds('');
        $quote->setCouponCode($couponCode);
        $this->updateTotals($quote);
    }
}
