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

namespace Bolt\Boltpay\ThirdPartyModules\Unirgy;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session as CheckoutSession;

class Giftcert
{
    const UNIRGY_GIFT_CERT = 'ugiftcert';

    /**
     * @var Discount
     */
    protected $discountHelper;

    /**
     * @var Bugsnag
     */
    protected $bugsnagHelper;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    protected $unirgyCertRepository;

    protected $unirgyGiftCertHelper;

    /**
     * Giftcert constructor.
     * @param Discount $discountHelper
     * @param Bugsnag $bugsnagHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param LogHelper $logHelper
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Discount $discountHelper,
        Bugsnag $bugsnagHelper,
        CartRepositoryInterface $quoteRepository,
        LogHelper $logHelper,
        CheckoutSession $checkoutSession
    )
    {
        $this->discountHelper = $discountHelper;
        $this->bugsnagHelper = $bugsnagHelper;
        $this->quoteRepository = $quoteRepository;
        $this->logHelper = $logHelper;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param $result
     * @param $unirgyCertRepository
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts($result,
                                     $unirgyCertRepository,
                                     $quote,
                                     $parentQuote,
                                     $paymentOnly)
    {
        $this->unirgyCertRepository = $unirgyCertRepository;
        list ($discounts, $totalAmount, $diff) = $result;
        $totalDiscount = $totals[self::UNIRGY_GIFT_CERT] ?? null;
        try {
            // Check whether the Mirasvit Store Credit is allowed for quote
            if ($totalDiscount && $amount = $totalDiscount->getValue() && $gcCode = $quote->getData('giftcert_code')) {
                ///////////////////////////////////////////////////////////////////////////
                /// Was added a proper Unirgy_Giftcert Amount to the discount.
                /// The GiftCert accumulate correct balance only after each collectTotals.
                ///  The Unirgy_Giftcert add the only discount which covers only product price.
                ///  We should get the whole balance at first of the Giftcert.
                ///////////////////////////////////////////////////////////////////////////
                $giftCertBalance = $this->getUnirgyGiftCertBalanceByCode($gcCode);
                $currencyCode = $quote->getQuoteCurrencyCode();
                if ($giftCertBalance > 0) {
                    $amount = $giftCertBalance;
                }
                $discountAmount = abs($amount);
                $roundedDiscountAmount = CurrencyUtils::toMinor($discountAmount, $currencyCode);
                $gcDescription = $totalDiscount->getTitle();
                $discountItem = [
                    'description' => $gcDescription,
                    'amount' => $roundedDiscountAmount,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_GIFTCARD,
                    'reference' => $gcCode,
                    'discount_type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type' => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                ];

                if (empty($gcCode)) {
                    $this->bugsnagHelper->notifyError('Empty discount code', "Info: {$gcDescription}");
                }
                $discounts[] = $discountItem;

                $diff -= CurrencyUtils::toMinorWithoutRounding($discountAmount, $currencyCode) - $roundedDiscountAmount;
                $totalAmount -= $roundedDiscountAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return [$discounts, $totalAmount, $diff];
        }
    }

    /**
     * @param $result
     * @param $unirgyGiftCertHelper
     * @param $code
     * @param $storeId
     * @return \Unirgy\Giftcert\Model\Cert|null
     */
    public function loadGiftcard($result, $unirgyGiftCertHelper, $code, $storeId)
    {
        $this->unirgyGiftCertHelper = $unirgyGiftCertHelper;

        if (!empty($result)) {
            return $result;
        }
        try {
            return $this->loadUnirgyGiftCertData($code, $storeId);
        } catch (LocalizedException $e) {
            return null;
        }
        return null;
    }

    /**
     * @param $result
     * @param $unirgyGiftCertHelper
     * @param $code
     * @param $giftCard
     * @param $immutableQuote
     * @param $parentQuote
     * @return array|null
     */
    public function applyGiftcard($result, $unirgyGiftCertHelper, $code, $giftCard, $immutableQuote, $parentQuote)
    {
        if (!empty($result)) {
            return $result;
        }
        if (!($giftCard instanceof \Unirgy\Giftcert\Model\Cert)) {
            return null;
        }
        $this->unirgyGiftCertHelper = $unirgyGiftCertHelper;

        try {
            if (empty($immutableQuote->getData($giftCard::GIFTCERT_CODE))) {
                $this->addUnirgyGiftCertToQuote($immutableQuote, $giftCard);
            }

            if (empty($parentQuote->getData($giftCard::GIFTCERT_CODE))) {
                $this->addUnirgyGiftCertToQuote($parentQuote, $giftCard);
            }

            // The Unirgy_GiftCert require double call the function addCertificate().
            // Look on Unirgy/Giftcert/Controller/Checkout/Add::execute()
            $this->addUnirgyGiftCertToQuote($this->checkoutSession->getQuote(), $giftCard);

            $result = [
                'status' => 'success',
                'discount_code' => $code,
                'discount_amount' => abs(
                    CurrencyUtils::toMinor($giftCard->getBalance(), $parentQuote->getQuoteCurrencyCode())
                ),
                'description' => __('Gift Card (%1)', $giftCard->getCode()),
                'discount_type' => 'fixed_amount',
            ];
            return $result;
        } catch (\Exception $e) {
            $result = [
                'status' => 'failure',
                'error_message' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * Get Unirgy_Giftcert balance.
     *
     * @param $giftcertCode
     * @return float
     */
    private function getUnirgyGiftCertBalanceByCode($giftcertCode)
    {
        $result = 0;

        $unirgyInstance = $this->unirgyCertRepository;

        if ($unirgyInstance) {

            $giftCodes = array_map('trim', explode(',', $giftcertCode));

            foreach ($giftCodes as $giftCode) {
                $giftCert = $unirgyInstance->get($giftCode);
                if ($giftCert && $giftCert->getStatus() === 'A' && $giftCert->getBalance() > 0) {
                    $result += $giftCert->getBalance();
                }
            }
        }

        return (float)$result;
    }

    /**
     * @param $code
     * @param $storeId
     * @return \Unirgy\Giftcert\Model\Cert|null
     */
    private function loadUnirgyGiftCertData($code, $storeId)
    {
        $result = null;

        /** @var \Unirgy\Giftcert\Model\GiftcertRepository $giftCertRepository */
        $giftCertRepository = $this->unirgyCertRepository;

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
    private function addUnirgyGiftCertToQuote($quote, $giftCard)
    {
        $unirgyHelper = $this->unirgyGiftCertHelper;

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
}
