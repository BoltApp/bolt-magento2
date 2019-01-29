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

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

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
     * @var ThirdPartyModuleFactory|\Unirgy\Giftcert\Model\GiftcertRepository
     */
    protected $unirgyCertRepository;

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
     * Discount constructor.
     *
     * @param Context $context
     * @param ResourceConnection $resource
     * @param ThirdPartyModuleFactory $amastyAccountFactory
     * @param ThirdPartyModuleFactory $amastyGiftCardManagement
     * @param ThirdPartyModuleFactory $amastyQuoteFactory
     * @param ThirdPartyModuleFactory $amastyQuoteResource
     * @param ThirdPartyModuleFactory $amastyQuoteRepository
     * @param ThirdPartyModuleFactory $amastyAccountCollection
     * @param CartRepositoryInterface $quoteRepository
     * @param ConfigHelper $configHelper
     * @param Bugsnag $bugsnag
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
        CartRepositoryInterface $quoteRepository,
        ConfigHelper $configHelper,
        Bugsnag $bugsnag
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
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->bugsnag = $bugsnag;
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

        if ($quote->getIsActive()) {

            $this->_eventManager->dispatch(
                'sales_quote_save_after',
                [
                    'quote' => $quote
                ]
            );
        }
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

            $connection->query($sql, ['entity_id' => $quote->getId(), 'bolt_parent_quote_id' => $quote->getId()]);
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
     * @param $giftcertCode
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getUnirgyGiftCertBalanceByCode($giftcertCode)
    {
        /** @var \Unirgy\Giftcert\Model\Cert $giftCert */
        $giftCert = $this->unirgyCertRepository->get($giftcertCode);

        $result = 0;
        if ($giftCert && $giftCert->getStatus() === 'A' && $giftCert->getBalance() > 0) {
            $result = $giftCert->getBalance();
        }

        return (float) $result;
    }
}
