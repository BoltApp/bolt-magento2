<?php

namespace Bolt\Custom\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class GiftcardWrapper used to wrap Aheadworks Giftcard {@see \Aheadworks\giftcard\Api\Data\GiftcardInterface}
 * and provide the same interface as Magento Giftcard for applying to quote
 */
class GiftcardWrapper
{
    /**
     * @var \Aheadworks\giftcard\Api\Data\GiftcardInterface wrapped Aheadworks Giftcard
     */
    private $giftcard;

    /**
     * @var \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface Aheadworks Giftcard management object
     */
    private $giftcardCartManagement;
    /**
     * @var \Bolt\Boltpay\Helper\Log
     */
    private $logHelper;

    /**
     * GiftcardWrapper constructor.
     *
     * @param \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface $giftcardCartManagement Aheadworks Giftcard management object
     * @param \Bolt\Boltpay\Helper\Log                                 $logHelper
     */
    public function __construct(
        \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface $giftcardCartManagement,
        \Bolt\Boltpay\Helper\Log $logHelper
    ) {
        $this->giftcardCartManagement = $giftcardCartManagement;
        $this->logHelper = $logHelper;
    }

    /**
     * Called from {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::applyingGiftCardCode}
     * applies wrapped giftcard to provided parent quote and sets giftcard amount
     *
     * @param bool                       $saveQuote unused flag
     * @param \Magento\Quote\Model\Quote $quote to apply giftcard to
     *
     * @throws CouldNotSaveException The specified Gift Card code not be added
     * @throws NoSuchEntityException Cart $cartId doesn't contain products
     * @throws NoSuchEntityException The specified Gift Card code is not valid
     * @throws LocalizedException The specified Gift Card code deactivated
     * @throws LocalizedException The specified Gift Card code expired
     * @throws LocalizedException The specified Gift Card code used
     */
    public function addToCart($saveQuote, $quote)
    {
        try {
            // on subsequest validation calls from Bolt checkout
            // try removing the gift card before adding it
            $this->giftcardCartManagement->remove($quote->getId(), $this->giftcard->getCode(), false);
        } catch (\Exception $e) {
            // gift card not yet added
        }
        $this->giftcardCartManagement->set($quote->getId(), $this->giftcard->getCode(), false);
        $quote->setGiftCardsAmount($this->giftcard->getBalance());
    }

    /**
     * Sets Aheadworks Giftcard instance to be applied to quote by {@see addToCart}
     * Called after instantiation by {@see \Bolt\Custom\Plugin\Model\Api\DiscountCodeValidationPlugin::aroundLoadGiftCardData}
     *
     * @param \Aheadworks\Giftcard\Api\Data\GiftcardInterface $giftcard instance to be wrapped
     *
     * @return $this
     */
    public function setGiftcard(\Aheadworks\Giftcard\Api\Data\GiftcardInterface $giftcard)
    {
        $this->giftcard = $giftcard;
        return $this;
    }

    /**
     * Forward all other method calls to the original instance (like getId)
     *
     * @param string $name of the method
     * @param array  $arguments of the method call
     *
     * @return mixed result of the wrapped instance method
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->giftcard, $name], $arguments);
    }
}