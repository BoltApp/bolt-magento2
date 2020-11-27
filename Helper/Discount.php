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
    // so we can treat 0.00499999 as the closest number to zero in M2.
    const MIN_NONZERO_VALUE = 0.00499999;

    /**
     * @var ResourceConnection $resource
     */
    private $resource;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyAccountFactory;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyGiftCardManagement;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyQuoteFactory;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyQuoteResource;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyQuoteRepository;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyLegacyAccountCollection;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyAccountFactory;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyGiftCardAccountManagement;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyGiftCardAccountCollection;

    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Model\GiftcertRepository
     */
    protected $unirgyCertRepository;
    
    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Helper\Data
     */
    protected $unirgyGiftCertHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyRewardsResourceQuote;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyRewardsQuote;

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
     * @var ThirdPartyModuleFactory
     */
    protected $moduleGiftCardAccount;
    
    /**
     * @var ThirdPartyModuleFactory
     */
    protected $moduleGiftCardAccountHelper;
    
    /**
     * @var CouponFactory
     */
    protected $couponFactory;
    
    /**
     * @var RuleRepository
     */
    protected $ruleRepository;

    /**
     * @var ThirdPartyModuleFactory Amasty GiftCard V2 quote extension attribute repository factory
     */
    private $amastyGiftCardAccountQuoteExtensionRepository;

    /**
     * @var EventsForThirdPartyModules
     */
    private $eventsForThirdPartyModules;

    /**
     * Discount constructor.
     * @param Context $context
     * @param ResourceConnection $resource
     * @param ThirdPartyModuleFactory $amastyLegacyAccountFactory
     * @param ThirdPartyModuleFactory $amastyLegacyGiftCardManagement
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteFactory
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteResource
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteRepository
     * @param ThirdPartyModuleFactory $amastyLegacyAccountCollection
     * @param ThirdPartyModuleFactory $amastyAccountFactory
     * @param ThirdPartyModuleFactory $amastyGiftCardAccountManagement
     * @param ThirdPartyModuleFactory $amastyGiftCardAccountCollection
     * @param ThirdPartyModuleFactory $amastyGiftCardAccountQuoteExtensionRepository
     * @param ThirdPartyModuleFactory $unirgyCertRepository
     * @param ThirdPartyModuleFactory $unirgyGiftCertHelper
     * @param ThirdPartyModuleFactory $amastyRewardsResourceQuote
     * @param ThirdPartyModuleFactory $amastyRewardsQuote
     * @param ThirdPartyModuleFactory $moduleGiftCardAccount
     * @param ThirdPartyModuleFactory $moduleGiftCardAccountHelper
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
        ThirdPartyModuleFactory $amastyLegacyAccountFactory,
        ThirdPartyModuleFactory $amastyLegacyGiftCardManagement,
        ThirdPartyModuleFactory $amastyLegacyQuoteFactory,
        ThirdPartyModuleFactory $amastyLegacyQuoteResource,
        ThirdPartyModuleFactory $amastyLegacyQuoteRepository,
        ThirdPartyModuleFactory $amastyLegacyAccountCollection,
        ThirdPartyModuleFactory $amastyAccountFactory,
        ThirdPartyModuleFactory $amastyGiftCardAccountManagement,
        ThirdPartyModuleFactory $amastyGiftCardAccountCollection,
        ThirdPartyModuleFactory $amastyGiftCardAccountQuoteExtensionRepository,
        ThirdPartyModuleFactory $unirgyCertRepository,
        ThirdPartyModuleFactory $unirgyGiftCertHelper,
        ThirdPartyModuleFactory $amastyRewardsResourceQuote,
        ThirdPartyModuleFactory $amastyRewardsQuote,
        ThirdPartyModuleFactory $moduleGiftCardAccount,
        ThirdPartyModuleFactory $moduleGiftCardAccountHelper,
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
        $this->amastyLegacyAccountFactory = $amastyLegacyAccountFactory;
        $this->amastyLegacyGiftCardManagement = $amastyLegacyGiftCardManagement;
        $this->amastyLegacyQuoteFactory = $amastyLegacyQuoteFactory;
        $this->amastyLegacyQuoteResource = $amastyLegacyQuoteResource;
        $this->amastyLegacyQuoteRepository = $amastyLegacyQuoteRepository;
        $this->amastyLegacyAccountCollection = $amastyLegacyAccountCollection;
        $this->amastyAccountFactory = $amastyAccountFactory;
        $this->amastyGiftCardAccountManagement = $amastyGiftCardAccountManagement;
        $this->amastyGiftCardAccountCollection = $amastyGiftCardAccountCollection;
        $this->unirgyCertRepository = $unirgyCertRepository;
        $this->unirgyGiftCertHelper = $unirgyGiftCertHelper;
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->appState = $appState;
        $this->sessionHelper = $sessionHelper;
        $this->logHelper = $logHelper;
        $this->moduleGiftCardAccount = $moduleGiftCardAccount;
        $this->moduleGiftCardAccountHelper = $moduleGiftCardAccountHelper;
        $this->couponFactory = $couponFactory;
        $this->ruleRepository = $ruleRepository;
        $this->amastyGiftCardAccountQuoteExtensionRepository = $amastyGiftCardAccountQuoteExtensionRepository;
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
     * Check whether the Amasty Gift Card module is available (installed and enabled)
     * @return bool
     */
    public function isAmastyGiftCardAvailable()
    {
        return ($this->amastyAccountFactory->isAvailable() || $this->amastyLegacyAccountFactory->isAvailable());
    }
    
    /**
     * Check whether the Amasty Gift Card module is legacy version
     * @return bool
     */
    public function isAmastyGiftCardLegacyVersion()
    {
        return $this->amastyLegacyAccountFactory->isExists();
    }

    /**
     * Remove Amasty Gift Card quote info
     *
     * @param Quote $quote
     */
    public function clearAmastyGiftCard($quote)
    {
        if (! $this->isAmastyGiftCardAvailable()) {
            return;
        }
        $connection = $this->resource->getConnection();
        try {
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');
            } else {
                $giftCardTable = $this->resource->getTableName('amasty_giftcard_quote');    
            }

            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :quote_id";
            $bind = [
                'quote_id' => $quote->getId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Try to clear Amasty Gift Cart data for the unused immutable quotes
     *
     * @param Quote $quote parent quote
     */
    public function deleteRedundantAmastyGiftCards($quote)
    {
        if (! $this->isAmastyGiftCardAvailable()) {
            return;
        }
        $connection = $this->resource->getConnection();
        try {
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');
            } else {
                $giftCardTable = $this->resource->getTableName('amasty_giftcard_quote');    
            }

            $quoteTable = $this->resource->getTableName('quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id IN 
                    (SELECT entity_id FROM {$quoteTable} 
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
            
            $bind = [
                'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
                'entity_id' => $quote->getBoltParentQuoteId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Remove Amasty Gift Card and update quote totals
     *
     * @param int $codeId
     * @param Quote $quote
     */
    public function removeAmastyGiftCard($codeId, $quote)
    {
        if (! $this->isAmastyGiftCardAvailable()) {
            return;
        }
        
        try {
            $connection = $this->resource->getConnection();
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');

                $sql = "DELETE FROM {$giftCardTable} WHERE code_id = :code_id AND quote_id = :quote_id";
                $connection->query($sql, ['code_id' => $codeId, 'quote_id' => $quote->getId()]);
    
                $this->updateTotals($quote);
            } else {
                if ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getAmGiftcardQuote()) {
                    $cards = $quote->getExtensionAttributes()->getAmGiftcardQuote()->getGiftCards();
                }

                $giftCodeExists = false;
                $giftCode = '';
                foreach ($cards as $k => $card) {
                    if($card['id'] == $codeId) {
                        $giftCodeExists = true;
                        $giftCode = $card['code'];
                        break;
                    }
                }
                
                if($giftCodeExists) {
                    $this->amastyGiftCardAccountManagement->getInstance()->removeGiftCardFromCart($quote->getId(), $giftCode);                   
                }                
            }
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
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
     * Check whether the Amasty Reward Points module is available (installed and enabled)
     *
     * @return bool
     */
    public function isAmastyRewardPointsAvailable()
    {
        return $this->amastyRewardsQuote->isAvailable();
    }

    /**
     * Copy Amasty Reward Points data from source to destination quote.
     * The reward points are fetched from the 3rd party module DB table (amasty_rewards_quote)
     * and assigned to the destination quote temporarily (not persistent to the quote table).
     * This is the reason there are cases when the reward points data is read for and applied
     * to the (source) quote itself. The data is needed to be set before the quote totals are calculated,
     * for example in the Shipping and Tax call.
     *
     * @param Quote $source
     * @param Quote|null $destination
     */
    public function setAmastyRewardPoints($source, $destination = null)
    {
        if (! $this->isAmastyRewardPointsAvailable()) {
            return;
        }
        if ($destination === null) {
            $destination = $source;
        }
        $amastyQuoteResourceModel = $this->amastyRewardsResourceQuote->getInstance();
        $amastyQuoteModel = $this->amastyRewardsQuote->getInstance();

        $amastyQuote = $amastyQuoteResourceModel->loadByQuoteId($source->getId());

        if ($amastyQuote) {
            $amastyRewardPoints = $amastyQuoteResourceModel->getUsedRewards($source->getId());
            $amastyQuoteModel->addReward($destination->getId(), $amastyRewardPoints);
            $destination->setAmrewardsPoint($amastyRewardPoints);
        }
    }

    /**
     * Try to clear Amasty Reward Points data for the immutable quotes
     *
     * @param Quote $quote parent quote
     */
    public function deleteRedundantAmastyRewardPoints($quote)
    {
        if (! $this->isAmastyRewardPointsAvailable()) {
            return;
        }
        $connection = $this->resource->getConnection();
        try {
            $rewardsTable = $this->resource->getTableName('amasty_rewards_quote');
            $quoteTable = $this->resource->getTableName('quote');

            $sql = "DELETE FROM {$rewardsTable} WHERE quote_id IN
                    (SELECT entity_id FROM {$quoteTable}
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
            $bind = [
                'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
                'entity_id' => $quote->getBoltParentQuoteId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Remove Amasty Reward Points quote info
     *
     * @param Quote $quote
     */
    public function clearAmastyRewardPoints($quote)
    {
        if (! $this->isAmastyRewardPointsAvailable()) {
            return;
        }
        $connection = $this->resource->getConnection();
        try {
            $rewardsTable = $this->resource->getTableName('amasty_rewards_quote');

            $sql = "DELETE FROM {$rewardsTable} WHERE quote_id = :quote_id";
            $bind = [
                'quote_id' => $quote->getId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnag->notifyException($e);
        }
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
        // Amasty reward points are held in a separate table
        // and are not assigned to the quote / totals directly out of the customer session.
        $this->setAmastyRewardPoints($quote);
        // Miravist reward points are held in a separate table
        // and are not assigned to the quote
        $this->eventsForThirdPartyModules->dispatchEvent("applyExternalDiscountData", $quote);
    }
    
    /**
     * Check if Magento_GiftCardAccount module is available
     *
     * @return bool true if module is available, else false
     */
    public function isMagentoGiftCardAccountAvailable()
    {
        return $this->moduleGiftCardAccount->isAvailable();
    }
    
    /**
     * Load the Magento_GiftCardAccount by code
     *
     * @param string $code
     * @param string|int $websiteId
     *
     * @return \Magento\GiftCardAccount\Model\Giftcardaccount|null
     */
    public function loadMagentoGiftCardAccount($code, $websiteId)
    {
        if (!$this->isMagentoGiftCardAccountAvailable()) {
            return null;
        }

        /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardAccountResource */
        $giftCardAccountResource = $this->moduleGiftCardAccount->getInstance();
        
        if (!$giftCardAccountResource) {
            return null;
        }

        $this->logHelper->addInfoLog('### GiftCard ###');
        $this->logHelper->addInfoLog('# Code: ' . $code);

        /** @var \Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection $giftCardsCollection */
        $giftCardsCollection = $giftCardAccountResource
            ->addFieldToFilter('code', ['eq' => $code])
            ->addWebsiteFilter([0, $websiteId]);

        /** @var \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard */
        $giftCard = $giftCardsCollection->getFirstItem();

        $result = (!$giftCard->isEmpty() && $giftCard->isValid()) ? $giftCard : null;

        $this->logHelper->addInfoLog('# loadMagentoGiftCardAccount Result is empty: '. ((!$result) ? 'yes' : 'no'));

        return $result;
    }
    
    /**
     * Get the Magento_GiftCardAccount Gift Card data from quote
     *
     * @param Quote $quote
     *
     * @return array
     */
    public function getMagentoGiftCardAccountGiftCardData($quote)
    {
        if (! $this->isMagentoGiftCardAccountAvailable()) {
            return [];
        }
        /** @var \Magento\GiftCardAccount\Helper\Data */
        $giftCardAccountHelper = $this->moduleGiftCardAccountHelper->getInstance();
        
        if (!$giftCardAccountHelper) {
            return [];
        }
        
        $cards = $giftCardAccountHelper->getCards($quote);

        if (!$cards) {
            $cards = [];
        } else {
            $cards = array_column($cards,
                                  defined( '\Magento\GiftCardAccount\Model\Giftcardaccount::AMOUNT' ) ? \Magento\GiftCardAccount\Model\Giftcardaccount::AMOUNT : 'a',
                                  defined( '\Magento\GiftCardAccount\Model\Giftcardaccount::CODE' ) ? \Magento\GiftCardAccount\Model\Giftcardaccount::CODE : 'c'
                                );
        }
      
        return $cards;
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
