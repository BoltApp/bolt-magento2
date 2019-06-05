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

    /**
     * @var ResourceConnection $resource
     */
    private $resource;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyAccountFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    private $amastyGiftCardManagement;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyQuoteFactory;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyQuoteResource;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyQuoteRepository;

    /**
     * @var ThirdPartyModuleFactory
     */
    protected $amastyAccountCollection;

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
    private $appState;

    /**
     * @var Session
     */
    private $sessionHelper;

    /**
     * Discount constructor.
     *
     * @param Context                 $context
     * @param ResourceConnection      $resource
     * @param ThirdPartyModuleFactory $amastyAccountFactory
     * @param ThirdPartyModuleFactory $amastyGiftCardManagement
     * @param ThirdPartyModuleFactory $amastyQuoteFactory
     * @param ThirdPartyModuleFactory $amastyQuoteResource
     * @param ThirdPartyModuleFactory $amastyQuoteRepository
     * @param ThirdPartyModuleFactory $amastyAccountCollection
     * @param ThirdPartyModuleFactory $unirgyCertRepository
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditHelper
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditCalculationHelper
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditCalculationConfig
     * @param ThirdPartyModuleFactory $mirasvitStoreCreditConfig
     * @param CartRepositoryInterface $quoteRepository
     * @param ConfigHelper            $configHelper
     * @param Bugsnag                 $bugsnag
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        ThirdPartyModuleFactory $amastyAccountFactory,
        ThirdPartyModuleFactory $amastyGiftCardManagement,
        ThirdPartyModuleFactory $amastyQuoteFactory,
        ThirdPartyModuleFactory $amastyQuoteResource,
        ThirdPartyModuleFactory $amastyQuoteRepository,
        ThirdPartyModuleFactory $amastyAccountCollection,
        ThirdPartyModuleFactory $unirgyCertRepository,
        ThirdPartyModuleFactory $mirasvitStoreCreditHelper,
        ThirdPartyModuleFactory $mirasvitStoreCreditCalculationHelper,
        ThirdPartyModuleFactory $mirasvitStoreCreditCalculationConfig,
        ThirdPartyModuleFactory $mirasvitStoreCreditConfig,
        ThirdPartyModuleFactory $mageplazaGiftCardCollection,
        ThirdPartyModuleFactory $mageplazaGiftCardFactory,
        CartRepositoryInterface $quoteRepository,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag,
        AppState $appState,
        Session $sessionHelper
    ) {
        parent::__construct($context);
        $this->resource = $resource;
        $this->amastyAccountFactory = $amastyAccountFactory;
        $this->amastyGiftCardManagement = $amastyGiftCardManagement;
        $this->amastyQuoteFactory = $amastyQuoteFactory;
        $this->amastyQuoteResource = $amastyQuoteResource;
        $this->amastyQuoteRepository = $amastyQuoteRepository;
        $this->amastyAccountCollection = $amastyAccountCollection;
        $this->unirgyCertRepository = $unirgyCertRepository;
        $this->mirasvitStoreCreditHelper = $mirasvitStoreCreditHelper;
        $this->mirasvitStoreCreditCalculationHelper = $mirasvitStoreCreditCalculationHelper;
        $this->mirasvitStoreCreditCalculationConfig = $mirasvitStoreCreditCalculationConfig;
        $this->mirasvitStoreCreditConfig = $mirasvitStoreCreditConfig;
        $this->mageplazaGiftCardCollection = $mageplazaGiftCardCollection;
        $this->mageplazaGiftCardFactory = $mageplazaGiftCardFactory;
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
        $this->appState = $appState;
        $this->sessionHelper = $sessionHelper;
    }

    /**
     * Check whether the Amasty Gift Card module is available (installed end enabled)
     * @return bool
     */
    public function isAmastyGiftCardAvailable()
    {
        return $this->amastyAccountFactory->isAvailable();
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
     * Load Amasty Gift Card account object.
     * @param string $code Gift Card coupon code
     * @return \Amasty\GiftCard\Model\Account|null
     */
    public function loadAmastyGiftCard($code)
    {
        try {
            if (!$this->isAmastyGiftCardAvailable()) {
                return null;
            }

            $accountModel = $this->amastyAccountFactory->getInstance()
                ->create()
                ->loadByCode($code);
            return $accountModel && $accountModel->getId() ? $accountModel : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Apply Amasty Gift Card coupon to cart
     *
     * @param string $code Gift Card coupon code
     * @param \Amasty\GiftCard\Model\Account $accountModel
     * @param Quote $quote
     * @return float
     * @throws LocalizedException
     */
    public function applyAmastyGiftCard($code, $accountModel, $quote)
    {
        // Get current gift card balance before applying it to the quote
        // in case "fixed_amount" / "pay for everything" discount type is used
        $giftAmount = $accountModel->getCurrentValue();

        $quoteId = $quote->getId();

        $isValid = $this->amastyGiftCardManagement->getInstance()->validateCode($quote, $code);

        if (!$isValid) {
            throw new LocalizedException(__('Coupon with specified code "%1" is not valid.', $code));
        }

        if ($accountModel->canApplyCardForQuote($quote)) {
            $quoteGiftCard = $this->amastyQuoteFactory->getInstance()->create();
            $this->amastyQuoteResource->getInstance()->load($quoteGiftCard, $quoteId, 'quote_id');
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

                $this->amastyQuoteRepository->getInstance()->save($quoteGiftCard);
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
            $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');

            // Clear previously applied gift cart codes from the immutable quote
            $sql = "DELETE FROM {$giftCardTable} WHERE quote_id = :destination_quote_id";
            $connection->query($sql, ['destination_quote_id' => $destinationQuoteId]);

            // Copy all gift cart codes applied to the parent quote to the immutable quote
            $sql = "INSERT INTO {$giftCardTable} (quote_id, code_id, account_id, base_gift_amount, code) 
                    SELECT :destination_quote_id, code_id, account_id, base_gift_amount, code
                    FROM {$giftCardTable} WHERE quote_id = :source_quote_id";
            $connection->query($sql, ['destination_quote_id' => $destinationQuoteId, 'source_quote_id' => $sourceQuoteId]);

            $connection->commit();
        } catch (\Zend_Db_Statement_Exception $e) {
            $connection->rollBack();
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
            $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');
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
        $connection = $this->resource->getConnection();
        try {
            $giftCardTable = $this->resource->getTableName('amasty_amgiftcard_quote');

            $sql = "DELETE FROM {$giftCardTable} WHERE code_id = :code_id AND quote_id = :quote_id";
            $connection->query($sql, ['code_id' => $codeId, 'quote_id' => $quote->getId()]);

            $this->updateTotals($quote);
        } catch (\Zend_Db_Statement_Exception $e) {
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
        $data = $this->amastyAccountCollection
            ->getInstance()
            ->joinCode()
            ->addFieldToFilter('gift_code', ['in'=>$giftCardCodes])
            ->getData();

        return array_sum(array_column($data, 'current_value'));
    }


    /**
     * Get Unirgy_Giftcert balance.
     *
     * @param $giftcertCode
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getUnirgyGiftCertBalanceByCode($giftcertCode)
    {
        /** @var \Unirgy\Giftcert\Model\Cert $giftCert */
        $unirgyInstance = $this->unirgyCertRepository->getInstance();

        $result = 0;
        if ($unirgyInstance) {
            $giftCert = $unirgyInstance->get($giftcertCode);
            if ($giftCert && $giftCert->getStatus() === 'A' && $giftCert->getBalance() > 0) {
                $result = $giftCert->getBalance();
            }
        }

        return (float) $result;
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

        return $quote->getCreditAmountUsed() > 0 && $this->getMirasvitStoreCreditBalanceAmount($quote) > 0;
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
        $miravitBalanceAmount = $this->getMirasvitStoreCreditBalanceAmount($quote);
        if(!$paymentOnly){
            /** @var \Mirasvit\Credit\Api\Config\CalculationConfigInterface $miravitCalculationConfig */
            $miravitCalculationConfig = $this->mirasvitStoreCreditCalculationConfig->getInstance();
            if ($miravitCalculationConfig->isTaxIncluded() || $miravitCalculationConfig->IsShippingIncluded()){
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
     * @param $quote
     *
     * @return float
     */
    protected function getMirasvitStoreCreditBalanceAmount($quote)
    {
        $balance = $this->mirasvitStoreCreditHelper
                        ->getInstance()
                        ->getBalance($quote->getCustomerId(), $quote->getQuoteCurrencyCode());

        $amount = $balance->getAmount();
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
    public function isMirasvitAdminQuoteUsingCreditObserver(Observer $observer){
        if(!$this->mirasvitStoreCreditConfig->isAvailable()){
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
        }catch (\Exception $e){}

        return false;
    }

    /**
     * Check whether the Mageplaza Gift Card module is available (installed end enabled)
     * @return bool
     */
    public function isMageplazaGiftCardAvailable()
    {
        return $this->mageplazaGiftCardFactory->isAvailable();
    }

    /**
     * Load Magplaza Gift Card account object.
     * @param string $code Gift Card coupon code
     * @return \Mageplaza\GiftCard\Model\GiftCard|null
     */
    public function loadMageplazaGiftCard($code)
    {
        if (!$this->isMageplazaGiftCardAvailable()) {
            return null;
        }

        try {
            $accountModel = $this->mageplazaGiftCardFactory->getInstance()
                ->load($code, 'code');
            return $accountModel && $accountModel->getId() ? $accountModel : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Mageplaza GiftCard Codes From the Session
     *
     * @param $quote
     * @return array
     */
    public function getMageplazaGiftCardCodesFromSession()
    {
        $giftCardsData = $this->sessionHelper->getCheckoutSession()->getGiftCardsData();

        return isset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) ? array_keys($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY]) : [];
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

            if($accountModel->getId() && isset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code])) {
                unset($giftCardsData[self::MAGEPLAZA_GIFTCARD_QUOTE_KEY][$code]);
                $this->sessionHelper->getCheckoutSession()->setGiftCardsData($giftCardsData);
                $quote->setData(self::MAGEPLAZA_GIFTCARD_QUOTE_KEY, NULL);
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
     * @param $quote
     */
    public function applyMageplazaDiscountToQuote($quote)
    {
        if (! $this->isMageplazaGiftCardAvailable()) {
            return;
        }

        try {
            if ($mpGiftCards = $quote->getData(self::MAGEPLAZA_GIFTCARD_QUOTE_KEY)) {
                foreach (json_decode($mpGiftCards, true) as $couponCode => $amount) {
                    $giftCard = $this->loadMageplazaGiftCard($couponCode);
                    if ($giftCard && $giftCard->getId()) {
                        $this->removeMageplazaGiftCard($giftCard->getId(), $quote);
                        $this->applyMageplazaGiftCard($giftCard->getCode(), $quote);
                    }
                }
            }
        }catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
