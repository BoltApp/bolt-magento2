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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Model\Payment;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use \Magento\Framework\Event\Observer;
use \Magento\Backend\App\Area\FrontNameResolver;
use \Magento\Framework\App\State as AppState;
use Bolt\Boltpay\Helper\Log as LogHelper;

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
    const BSS_STORE_CREDIT = 'bss_storecredit';

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
     * @var ThirdPartyModuleFactory
     */
    protected $mageplazaGiftCardCollection;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $mageplazaGiftCardFactory;

    /**
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Model\GiftcertRepository
     */
    protected $unirgyCertRepository;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $mirasvitStoreCreditHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $mirasvitStoreCreditCalculationHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $mirasvitStoreCreditCalculationConfig;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $mirasvitStoreCreditConfig;

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
     * @var ThirdPartyModuleFactory
     */
    protected $bssStoreCreditHelper;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $bssStoreCreditCollection;

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
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditHelper
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditCalculationHelper
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditCalculationConfig
     * @param ThirdPartyModuleFactory $mirasvitRewardsPurchaseHelper
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditConfig
     * @param ThirdPartyModuleFactory $mageplazaGiftCardCollection
     * @param ThirdPartyModuleFactory $mageplazaGiftCardFactory
     * @param ThirdPartyModuleFactory $amastyRewardsResourceQuote
     * @param ThirdPartyModuleFactory $amastyRewardsQuote
     * @param ThirdPartyModuleFactory $aheadworksCustomerStoreCreditManagement
     * @param ThirdPartyModuleFactory $bssStoreCreditHelper
     * @param ThirdPartyModuleFactory $bssStoreCreditCollection
     * @param CartRepositoryInterface $quoteRepository
     * @param ConfigHelper            $configHelper
     * @param Bugsnag                 $bugsnag
     * @param AppState                $appState
     * @param Session                 $sessionHelper
     * @param LogHelper               $logHelper
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
        ThirdPartyModuleFactory $mirasvitStoreCreditHelper,
        ThirdPartyModuleFactory $mirasvitStoreCreditCalculationHelper,
        ThirdPartyModuleFactory $mirasvitStoreCreditCalculationConfig,
        ThirdPartyModuleFactory $mirasvitStoreCreditConfig,
        ThirdPartyModuleFactory $mirasvitRewardsPurchaseHelper,
        ThirdPartyModuleFactory $mageplazaGiftCardCollection,
        ThirdPartyModuleFactory $mageplazaGiftCardFactory,
        ThirdPartyModuleFactory $amastyRewardsResourceQuote,
        ThirdPartyModuleFactory $amastyRewardsQuote,
        ThirdPartyModuleFactory $aheadworksCustomerStoreCreditManagement,
        ThirdPartyModuleFactory $bssStoreCreditHelper,
        ThirdPartyModuleFactory $bssStoreCreditCollection,
        CartRepositoryInterface $quoteRepository,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        AppState $appState,
        Session $sessionHelper,
        LogHelper $logHelper
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
        $this->mirasvitStoreCreditHelper = $mirasvitStoreCreditHelper;
        $this->mirasvitStoreCreditCalculationHelper = $mirasvitStoreCreditCalculationHelper;
        $this->mirasvitStoreCreditCalculationConfig = $mirasvitStoreCreditCalculationConfig;
        $this->mirasvitStoreCreditConfig = $mirasvitStoreCreditConfig;
        $this->mirasvitRewardsPurchaseHelper = $mirasvitRewardsPurchaseHelper;
        $this->mageplazaGiftCardCollection = $mageplazaGiftCardCollection;
        $this->mageplazaGiftCardFactory = $mageplazaGiftCardFactory;
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;
        $this->aheadworksCustomerStoreCreditManagement = $aheadworksCustomerStoreCreditManagement;
        $this->bssStoreCreditHelper = $bssStoreCreditHelper;
        $this->bssStoreCreditCollection = $bssStoreCreditCollection;
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->appState = $appState;
        $this->sessionHelper = $sessionHelper;
        $this->logHelper = $logHelper;
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
        if ($amastyGiftCardConfig && @$amastyGiftCardConfig->payForEverything === false) {
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
     * Check whether the Bss Store Credit module is allowed
     *
     * @return int
     */
    public function isBssStoreCreditAllowed()
    {
        if (!$this->bssStoreCreditHelper->isAvailable()) {
            return 0;
        }
        return $this->bssStoreCreditHelper->getInstance()->getGeneralConfig('active');
    }

    /**
     * @param $immutableQuote
     * @param $parentQuote
     * @return mixed
     */
    public function getBssStoreCreditAmount($immutableQuote, $parentQuote)
    {
        try {
            $bssStoreCreditHelper = $this->bssStoreCreditHelper->getInstance();
            $isAppliedToShippingAndTax = $bssStoreCreditHelper->getGeneralConfig('used_shipping') || $bssStoreCreditHelper->getGeneralConfig('used_tax');

            $storeCreditAmount = $immutableQuote->getBaseBssStorecreditAmountInput();
            if ($isAppliedToShippingAndTax && abs($storeCreditAmount) >= $immutableQuote->getSubtotal()) {
                $storeCreditAmount = $this->getBssStoreCreditBalanceAmount($parentQuote);
                $parentQuote->setBaseBssStorecreditAmountInput($storeCreditAmount)->save();
                $immutableQuote->setBaseBssStorecreditAmountInput($storeCreditAmount)->save();
                ;
            }

            return $storeCreditAmount;
        } catch (\Exception $exception) {
            $this->bugsnag->notifyException($exception);
            return 0;
        }
    }

    /**
     * @param $quote
     * @return float|int
     */
    public function getBssStoreCreditBalanceAmount($quote)
    {
        $data = $this->bssStoreCreditCollection
            ->getInstance()
            ->addFieldToFilter('customer_id', ['in' => $quote->getCustomerId()])
            ->getData();

        return array_sum(array_column($data, 'balance_amount'));
    }

    /**
     * Check whether the Mirasvit Store Credit module is allowed for quote
     *
     * @param $quote
     *
     * @return bool
     */
    public function isMirasvitStoreCreditAllowed($quote)
    {
        if (!$this->mirasvitStoreCreditHelper->isAvailable()) {
            return false;
        }

        return $quote->getCreditAmountUsed() > 0 && $this->getMirasvitStoreCreditUsedAmount($quote) > 0;
    }

    /**
     * @param      $quote
     *
     * @param bool $paymentOnly
     *
     * @return float
     */
    public function getMirasvitStoreCreditAmount($quote, $paymentOnly = false)
    {
        $miravitBalanceAmount = $this->getMirasvitStoreCreditUsedAmount($quote);
        if (!$paymentOnly) {
            /** @var \Mirasvit\Credit\Api\Config\CalculationConfigInterface $miravitCalculationConfig */
            $miravitCalculationConfig = $this->mirasvitStoreCreditCalculationConfig->getInstance();
            if ($miravitCalculationConfig->isTaxIncluded() || $miravitCalculationConfig->IsShippingIncluded()) {
                return $miravitBalanceAmount;
            }
        }

        $unresolvedTotal = $quote->getGrandTotal() + $quote->getCreditAmountUsed();
        $totals = $quote->getTotals();

        $tax      = isset($totals['tax']) ? $totals['tax']->getValue() : 0;
        $shipping = isset($totals['shipping']) ? $totals['shipping']->getValue() : 0;

        $unresolvedTotal = $this->mirasvitStoreCreditCalculationHelper->getInstance()->calc($unresolvedTotal, $tax, $shipping);

        return min($unresolvedTotal, $miravitBalanceAmount);
    }

    /**
     * Get Mirasvit Store credit balance used amount.
     * This method is only called when the Mirasvit_Credit module is installed and available on the quote.
     *
     * @param $quote
     *
     * @return float
     */
    protected function getMirasvitStoreCreditUsedAmount($quote)
    {
        $balance = $this->mirasvitStoreCreditHelper
                        ->getInstance()
                        ->getBalance($quote->getCustomerId(), $quote->getQuoteCurrencyCode());

        $amount = ((float)$quote->getManualUsedCredit() > 0) ? $quote->getManualUsedCredit() : $balance->getAmount();
        if ($quote->getQuoteCurrencyCode() !== $balance->getCurrencyCode()) {
            $amount = $this->mirasvitStoreCreditCalculationHelper->getInstance()->convertToCurrency(
                $amount,
                $balance->getCurrencyCode(),
                $quote->getQuoteCurrencyCode(),
                $quote->getStore()
            );
        }

        return $amount;
    }

    /**
     * @param Observer $observer
     *
     * @return bool
     */
    public function isMirasvitAdminQuoteUsingCreditObserver(Observer $observer)
    {
        if (!$this->mirasvitStoreCreditConfig->isAvailable()) {
            return false;
        }

        try {
            $payment = $observer->getEvent()->getPayment();
            $miravitConfig = $this->mirasvitStoreCreditConfig->getInstance();

            if ($payment->getMethod() == Payment::METHOD_CODE &&
                $this->appState->getAreaCode() == FrontNameResolver::AREA_CODE &&
                $payment->getQuote()->getUseCredit() == $miravitConfig::USE_CREDIT_YES
            ) {
                return true;
            }
        } catch (\Exception $e) {

        }

        return false;
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
     * Check whether the Mageplaza Gift Card module is available (installed and enabled)
     * @return bool
     */
    public function isMageplazaGiftCardAvailable()
    {
        return $this->mageplazaGiftCardFactory->isAvailable();
    }

    /**
     * Load Magplaza Gift Card account object.
     * @param string $code Gift Card coupon code
     * @param string|int $storeId
     * @return \Mageplaza\GiftCard\Model\GiftCard|null
     */
    public function loadMageplazaGiftCard($code, $storeId)
    {
        if (!$this->isMageplazaGiftCardAvailable()) {
            return null;
        }

        try {
            $accountModel = $this->mageplazaGiftCardFactory->getInstance()
                ->load($code, 'code');

            return $accountModel && $accountModel->getId()
                   && (! $accountModel->getStoreId() || $accountModel->getStoreId() == $storeId)
                   ? $accountModel : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Mageplaza GiftCard Codes
     *
     * @param $quote
     * @return array
     * @throws LocalizedException
     */
    public function getMageplazaGiftCardCodes($quote)
    {
        $giftCardsData = $this->sessionHelper->getCheckoutSession()->getGiftCardsData();
        $giftCardCodes = isset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) ? array_keys($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) : [];

        $giftCardsQuote = $quote->getMpGiftCards();
        if (!$giftCardCodes && $giftCardsQuote) {
            $giftCardCodes = array_keys(json_decode($giftCardsQuote, true));
        }

        return $giftCardCodes;
    }

    /**
     * Get accumulated balance of all applied Mageplaza Gift Cards
     *
     * @param $giftCardCodes
     * @return float
     */
    public function getMageplazaGiftCardCodesCurrentValue($giftCardCodes)
    {
        $data = $this->mageplazaGiftCardCollection
            ->getInstance()
            ->addFieldToFilter('code', ['in' => $giftCardCodes])
            ->getData();

        return array_sum(array_column($data, 'balance'));
    }

    /**
     * Remove Mageplaza Gift Card and update quote totals
     *
     * @param int $codeId
     * @param Quote $quote
     */
    public function removeMageplazaGiftCard($codeId, $quote)
    {
        if (! $this->isMageplazaGiftCardAvailable()) {
            return;
        }

        try {
            $accountModel = $this->mageplazaGiftCardFactory->getInstance()
                ->load($codeId);

            $giftCardsData = $this->sessionHelper->getCheckoutSession()->getGiftCardsData();
            $code = $accountModel->getCode();

            if ($accountModel->getId() && isset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code])) {
                unset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code]);
                $this->sessionHelper->getCheckoutSession()->setGiftCardsData($giftCardsData);
                $quote->setData(self::MAGEPLAZA_GIFTCARD_QUOTE_KEY, null);
                $this->updateTotals($quote);
            }

        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Apply Mageplaza Gift Card coupon to cart
     *
     * @param $code
     * @param $quote
     * @return int|void
     */
    public function applyMageplazaGiftCard($code, $quote)
    {
        if (! $this->isMageplazaGiftCardAvailable()) {
            return;
        }

        try {
            $giftCardsData = $this->sessionHelper->getCheckoutSession()->getGiftCardsData();
            $giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code] = 0;
            $this->sessionHelper->getCheckoutSession()->setGiftCardsData($giftCardsData);
            $this->updateTotals($quote);
            $totals = $quote->getTotals();
            return isset($totals[self::MAGEPLAZA_GIFTCARD]) ? $totals[self::MAGEPLAZA_GIFTCARD]->getValue() : 0;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Apply Mageplaza Gift Card to the quote
     *
     * @param Quote $quote
     */
    public function applyMageplazaDiscountToQuote($quote)
    {
        if (! $this->isMageplazaGiftCardAvailable()) {
            return;
        }

        try {
            if ($mpGiftCards = $quote->getData(self::MAGEPLAZA_GIFTCARD_QUOTE_KEY)) {
                foreach (json_decode($mpGiftCards, true) as $couponCode => $amount) {
                    $giftCard = $this->loadMageplazaGiftCard($couponCode, $quote->getStoreId());
                    if ($giftCard && $giftCard->getId()) {
                        $this->removeMageplazaGiftCard($giftCard->getId(), $quote);
                        $this->applyMageplazaGiftCard($giftCard->getCode(), $quote);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
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
}
