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
    const AMASTY_STORECREDIT = 'amstorecredit';
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
     * Mirasvit Rewards Points entry point
     *
     * @var ThirdPartyModuleFactory
     */
    protected $mirasvitRewardsPurchaseHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyRewardsResourceQuote;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyRewardsQuote;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $aheadworksCustomerStoreCreditManagement;

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
     * Discount constructor.
     *
     * @param Context                 $context
     * @param ResourceConnection      $resource
     * @param ThirdPartyModuleFactory $amastyLegacyAccountFactory
     * @param ThirdPartyModuleFactory $amastyLegacyGiftCardManagement
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteFactory
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteResource
     * @param ThirdPartyModuleFactory $amastyLegacyQuoteRepository
     * @param ThirdPartyModuleFactory $amastyLegacyAccountCollection
     * @param ThirdPartyModuleFactory $amastyAccountFactory
     * @param ThirdPartyModuleFactory $amastyGiftCardAccountManagement
     * @param ThirdPartyModuleFactory $amastyGiftCardAccountCollection
     * @param ThirdPartyModuleFactory $unirgyCertRepository
     * @param ThirdPartyModuleFactory $unirgyGiftCertHelper
     * @param ThirdPartyModuleFactory $mirasvitRewardsPurchaseHelper
     * @param ThirdPartyModuleFactory $amastyRewardsResourceQuote
     * @param ThirdPartyModuleFactory $amastyRewardsQuote
     * @param ThirdPartyModuleFactory $aheadworksCustomerStoreCreditManagement
     * @param ThirdPartyModuleFactory $moduleGiftCardAccount
     * @param ThirdPartyModuleFactory $moduleGiftCardAccountHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param ConfigHelper            $configHelper
     * @param Bugsnag                 $bugsnag
     * @param AppState                $appState
     * @param Session                 $sessionHelper
     * @param LogHelper               $logHelper
     * @param CouponFactory           $couponFactory
     * @param RuleRepository          $ruleRepository
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
        ThirdPartyModuleFactory $unirgyCertRepository,
        ThirdPartyModuleFactory $unirgyGiftCertHelper,
        ThirdPartyModuleFactory $mirasvitRewardsPurchaseHelper,
        ThirdPartyModuleFactory $amastyRewardsResourceQuote,
        ThirdPartyModuleFactory $amastyRewardsQuote,
        ThirdPartyModuleFactory $aheadworksCustomerStoreCreditManagement,
        ThirdPartyModuleFactory $moduleGiftCardAccount,
        ThirdPartyModuleFactory $moduleGiftCardAccountHelper,
        CartRepositoryInterface $quoteRepository,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        AppState $appState,
        Session $sessionHelper,
        LogHelper $logHelper,
        CouponFactory $couponFactory,
        RuleRepository $ruleRepository
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
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;
        $this->aheadworksCustomerStoreCreditManagement = $aheadworksCustomerStoreCreditManagement;
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
     * Load Amasty Gift Card account object.
     * @param string $code Gift Card coupon code
     * @param string|int $websiteId
     * @return \Amasty\GiftCard\Model\Account|null
     */
    public function loadAmastyGiftCard($code, $websiteId)
    {
        try {
            if (!$this->isAmastyGiftCardAvailable()) {
                return null;
            }
            
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $accountModel = $this->amastyLegacyAccountFactory->getInstance()
                    ->create()
                    ->loadByCode($code);
            } else {
                $accountModel = $this->amastyAccountFactory->getInstance()
                    ->create()
                    ->getByCode($code);    
            }
                
            return $accountModel && $accountModel->getId()
                   && (! $accountModel->getWebsiteId() || $accountModel->getWebsiteId() == $websiteId)
                   ? $accountModel : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Apply Amasty Gift Card coupon to cart
     *
     * @param string $code Gift Card coupon code
     * @param \Amasty\GiftCardAccount\Model\GiftCardAccount\Account $accountModel
     * @param Quote $quote
     * @return float
     * @throws LocalizedException
     */
    public function applyAmastyGiftCard($code, $accountModel, $quote)
    {
        if (!$this->isAmastyGiftCardAvailable()) {
            return null;
        }
            
        // Get current gift card balance before applying it to the quote
        // in case "fixed_amount" / "pay for everything" discount type is used
        $giftAmount = $accountModel->getCurrentValue();

        $quoteId = $quote->getId();
        
        if ($this->isAmastyGiftCardLegacyVersion()) {
            $isValid = $this->amastyLegacyGiftCardManagement->getInstance()->validateCode($quote, $code);

            if (!$isValid) {
                throw new LocalizedException(__('Coupon with specified code "%1" is not valid.', $code));
            }
    
            if ($accountModel->canApplyCardForQuote($quote)) {
                $quoteGiftCard = $this->amastyLegacyQuoteFactory->getInstance()->create();
                $this->amastyLegacyQuoteResource->getInstance()->load($quoteGiftCard, $quoteId, 'quote_id');
                $subtotal = $quoteGiftCard->getSubtotal($quote);
    
                if ($quoteGiftCard->getCodeId() && $accountModel->getCodeId() == $quoteGiftCard->getCodeId()) {
    
                    throw new LocalizedException(__('This gift card account is already in the quote.'));
    
                } elseif ($quoteGiftCard->getGiftAmount() && $subtotal == $quoteGiftCard->getGiftAmount()) {
    
                    throw new LocalizedException(__('Gift card can\'t be applied. Maximum discount reached.'));
    
                } else {
                    $quoteGiftCard->unsetData($quoteGiftCard->getIdFieldName());
                    $quoteGiftCard->setQuoteId($quoteId);
                    $quoteGiftCard->setCodeId($accountModel->getCodeId());
                    $quoteGiftCard->setAccountId($accountModel->getId());
    
                    $this->amastyLegacyQuoteRepository->getInstance()->save($quoteGiftCard);
                    $this->updateTotals($quote);
    
                    if ($this->getAmastyPayForEverything()) {
                        // pay for everything, items, shipping, tax
                        return $giftAmount;
                    } else {
                        // pay for items only
                        $totals = $quote->getTotals();
                        return $totals[self::AMASTY_GIFTCARD]->getValue();
                    }
                }
            } else {
                throw new LocalizedException(__('Gift card can\'t be applied.'));
            }
        } else {
            $giftCardCode = $this->amastyGiftCardAccountManagement->getInstance()->applyGiftCardToCart($quoteId, $code);
            
            if ($this->getAmastyPayForEverything()) {
                // pay for everything, items, shipping, tax
                return $giftAmount;
            } else {
                $activeQuote = $this->quoteRepository->getActive($quoteId);
                // pay for items only
                $totals = $activeQuote->getTotals();

                return $totals[self::AMASTY_GIFTCARD]->getValue();
            }
        }        
    }
    
    /**
     * Copy Amasty Gift Cart data from source to destination quote
     *
     * @param int|string $sourceQuoteId
     * @param int|string $destinationQuoteId
     */
    public function cloneAmastyGiftCards($sourceQuoteId, $destinationQuoteId)
    {
        if (! $this->isAmastyGiftCardAvailable()) {
            return;
        }
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');

                // Clear previously applied gift cart codes from the immutable quote
                $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :destination_quote_id";
                $connection->query($sql, ['destination_quote_id' => $destinationQuoteId]);
    
                // Copy all gift cart codes applied to the parent quote to the immutable quote
                $sql = "INSERT INTO {$giftCardTable} (quote_id, code_id, account_id, base_gift_amount, code) 
                        SELECT :destination_quote_id, code_id, account_id, base_gift_amount, code
                        FROM {$giftCardTable} WHERE quote_id = :source_quote_id";
            } else {
                $giftCardTable = $this->resource->getTableName('amasty_giftcard_quote');
    
                // Clear previously applied gift cart codes from the immutable quote
                $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :destination_quote_id";
                $connection->query($sql, ['destination_quote_id' => $destinationQuoteId]);
    
                // Copy all gift cart codes applied to the parent quote to the immutable quote
                $sql = "INSERT INTO {$giftCardTable} (quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used) 
                        SELECT :destination_quote_id, gift_cards, gift_amount, base_gift_amount, gift_amount_used, base_gift_amount_used
                        FROM {$giftCardTable} WHERE quote_id = :source_quote_id";
            }

            $connection->query($sql, ['destination_quote_id' => $destinationQuoteId, 'source_quote_id' => $sourceQuoteId]);

            $connection->commit();
        } catch (\Zend_Db_Statement_Exception $e) {
            $connection->rollBack();
            $this->bugsnag->notifyException($e);
        }
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
     * Get Amasty Gift Card codes, stored comma separated in total title field, return as array.
     *
     * @param array $totals totals array collected from quote
     * @return array
     */
    public function getAmastyGiftCardCodesFromTotals($totals)
    {
        $giftCardCodes = explode(',', $totals[self::AMASTY_GIFTCARD]->getTitle());
        return array_map('trim', $giftCardCodes);
    }

    /**
     * Get accumulated unused balance of all Amasty Gift Cards corresponding to passed gift card coupons array
     *
     * @param array $giftCardCodes
     * @return float|int|mixed
     */
    public function getAmastyGiftCardCodesCurrentValue($giftCardCodes)
    {
        if (! $this->isAmastyGiftCardAvailable()) {
            return;
        }
        
        try {
            if ($this->isAmastyGiftCardLegacyVersion()) {
                $data = $this->amastyLegacyAccountCollection
                    ->getInstance()
                    ->joinCode()
                    ->addFieldToFilter('gift_code', ['in'=>$giftCardCodes])
                    ->getData();
            } else {
                $data = $this->amastyGiftCardAccountCollection
                    ->getInstance()
                    ->addCodeTable()
                    ->addFieldToFilter('code', ['in'=>$giftCardCodes])
                    ->getData();    
            }        
    
            return array_sum(array_column($data, 'current_value'));
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            return null;
        }
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
     * If enabled, gets the Mirasvit Rewards amount used
     *
     * @param \Magento\Quote\Model\Quote $quote  The parent quote of this order which contains Rewards points references
     *
     * @return float  If enabled, the currency amount used in the order, otherwise 0
     */
    public function getMirasvitRewardsAmount($quote)
    {
        /** @var \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper */
        $mirasvitRewardsPurchaseHelper = $this->mirasvitRewardsPurchaseHelper->getInstance();

        if (!$mirasvitRewardsPurchaseHelper) {
            return 0;
        }

        $miravitRewardsPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($quote);
        return $miravitRewardsPurchase->getSpendAmount();
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
     * Check if Aheadworks_StoreCredit module is available
     *
     * @return bool true if module is available, else false
     */
    public function isAheadworksStoreCreditAvailable()
    {
        return $this->aheadworksCustomerStoreCreditManagement->isAvailable();
    }

    /**
     * Get Aheadworks store credit for the user
     *
     * @param int $customerId Logged in customer ID
     * @return float User store credit
     */
    public function getAheadworksStoreCredit($customerId)
    {
        if (! $this->isAheadworksStoreCreditAvailable()) {
            return 0;
        }
        return $this->aheadworksCustomerStoreCreditManagement
                    ->getInstance()
                    ->getCustomerStoreCreditBalance($customerId);
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
        $this->applyMiravistRewardPoint($quote);
    }

    /**
     * Copy Miravist Reward Point data from parent quote to immutable quote.
     * The reward points are fetched from the 3rd party module DB table (mst_rewards_purchase)
     * and assigned to the parent quote temporarily (they are not persisted in the quote table).
     * The data needs to be set on the immutable quote before the quote totals are calculated
     * in the Shipping and Tax call in order to get correct tax
     *
     * @param $immutableQuote
     */
    public function applyMiravistRewardPoint($immutableQuote)
    {
        $parentQuoteId = $immutableQuote->getBoltParentQuoteId();
        /** @var \Mirasvit\Rewards\Helper\Purchase $mirasvitRewardsPurchaseHelper */
        $mirasvitRewardsPurchaseHelper = $this->mirasvitRewardsPurchaseHelper->getInstance();
        if (!$mirasvitRewardsPurchaseHelper || !$parentQuoteId) {
            return;
        }

        try {
            $parentPurchase = $mirasvitRewardsPurchaseHelper->getByQuote($parentQuoteId);
            if (abs($parentPurchase->getSpendAmount()) > 0) {
                $mirasvitRewardsPurchaseHelper->getByQuote($immutableQuote)
                    ->setSpendPoints($parentPurchase->getSpendPoints())
                    ->setSpendMinAmount($parentPurchase->getSpendMinAmount())
                    ->setSpendMaxAmount($parentPurchase->getSpendMaxAmount())
                    ->setSpendAmount($parentPurchase->getSpendAmount())
                    ->setBaseSpendAmount($parentPurchase->getBaseSpendAmount())
                    ->save();
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
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
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode)->collectTotals()->save();
    }
}
