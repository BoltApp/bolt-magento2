<?php

namespace Bolt\Custom\Plugin\Model\Api;

/**
 * Plugin for {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation}
 * Used to add support for applying Aheadworks Giftcards from Bolt modal
 */
class DiscountCodeValidationPlugin
{
    /**
     * @var \Aheadworks\Giftcard\Api\GiftcardRepositoryInterface Aheadworks Giftcard repository object
     */
    private $giftcardRepository;

    /**
     * @var \Bolt\Custom\Model\GiftcardWrapperFactory factory instance for {@see \Bolt\Custom\Model\GiftcardWrapper}
     */
    private $giftcardWrapperFactory;

    /**
     * Class constructor
     *
     * @param \Aheadworks\Giftcard\Api\GiftcardRepositoryInterface $giftcardRepository
     * @param \Bolt\Custom\Model\GiftcardWrapperFactory            $giftcardWrapperFactory
     */
    public function __construct(
        \Aheadworks\Giftcard\Api\GiftcardRepositoryInterface $giftcardRepository,
        \Bolt\Custom\Model\GiftcardWrapperFactory $giftcardWrapperFactory
    ) {
        $this->giftcardRepository = $giftcardRepository;
        $this->giftcardWrapperFactory = $giftcardWrapperFactory;
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::loadGiftCardData}
     * Attempts to load Aheadworks Giftcard and, if found, returns an instance of the Giftcard object wrapped in
     * {@see \Bolt\Custom\Model\GiftcardWrapper} to provide the same interface as Magento Giftcard
     * Otherwise returns the result of the original(plugged) method
     *
     * @param \Bolt\Boltpay\Model\Api\DiscountCodeValidation $subject plugged Bolt Discount Code Validation object
     * @param callable                                       $proceed original method (or next plugin in line) reference
     * @param string                                         $code potential giftcard code
     * @param int                                            $websiteId quote website id
     *
     * @return \Bolt\Custom\Model\GiftcardWrapper|\Magento\GiftCardAccount\Model\Giftcardaccount|null
     */
    public function aroundLoadGiftCardData(\Bolt\Boltpay\Model\Api\DiscountCodeValidation $subject, callable $proceed, $code, $websiteId)
    {
        try {
            $giftcard = $this->giftcardRepository->getByCode($code, $websiteId);
            return $this->giftcardWrapperFactory->create()->setGiftcard($giftcard);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Aheadworks Giftcard not found, proceed to original method call
        }
        return $proceed($code, $websiteId);
    }
}