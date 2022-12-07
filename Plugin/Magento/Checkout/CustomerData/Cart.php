<?php
namespace Bolt\Boltpay\Plugin\Magento\Checkout\CustomerData;

use Magento\Checkout\CustomerData\Cart as CustomerDataCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Helper\Cart as BoltHelperCart;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;

/**
 * Process quote bolt data
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Cart
{
    private const HINTS_TYPE = 'cart';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var QuoteIdMaskResourceModel
     */
    private $quoteIdMaskResourceModel;

    /**
     * @var BoltHelperCart
     */
    private $boltHelperCart;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Decider
     */
    private $featureSwitches;

    /**
     * @param CheckoutSession $checkoutSession
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param BoltHelperCart $boltHelperCart
     * @param Bugsnag $bugsnag
     * @param Decider $featureSwitches
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        BoltHelperCart $boltHelperCart,
        Bugsnag $bugsnag,
        Decider $featureSwitches
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->boltHelperCart = $boltHelperCart;
        $this->bugsnag = $bugsnag;
        $this->featureSwitches = $featureSwitches;
    }

    /**
     * Add bolt data to result
     *
     * @param CustomerDataCart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(CustomerDataCart $subject, array $result)
    {
        if (!$this->featureSwitches->isEnabledFetchCartViaApi()) {
            return $result;
        }
        $quote = $this->getQuote();
        $result['quoteMaskedId'] = null;
        $result['boltCartHints'] = null;
        if ($quote->getId()) {
            try {
                $result['quoteMaskedId'] = $this->getQuoteMaskedId((int)$quote->getId());
                $result['boltCartHints'] = $this->boltHelperCart->getHints($quote->getId(), self::HINTS_TYPE);
            } catch (\Exception $e) {
                $this->bugsnag->notifyException($e);
            }
        }
        return $result;
    }

    /**
     * Get active quote
     *
     * @return Quote
     */
    private function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Returns exists or generate new quote masked id
     *
     * @param int $quoteId
     * @return string
     * @throws AlreadyExistsException
     */
    private function getQuoteMaskedId(int $quoteId): string
    {
        try {
            $maskedId = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        } catch (NoSuchEntityException $e) {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $quoteIdMask->setQuoteId($quoteId);
            $this->quoteIdMaskResourceModel->save($quoteIdMask);
            $maskedId = $quoteIdMask->getMaskedId();
        }
        return $maskedId;
    }
}
