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

namespace Bolt\Boltpay\Plugin\ThirdPartySupport\Aheadworks\Giftcard\V1;

use Aheadworks\Giftcard\Api\Data\GiftcardInterface;
use Aheadworks\Giftcard\Api\GiftcardCartManagementInterface;
use Aheadworks\Giftcard\Api\GiftcardRepositoryInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\Api\DiscountCodeValidation;
use Bolt\Boltpay\Model\ErrorResponse;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin;
use Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\GiftCardAccount\Model\Giftcardaccount;
use Magento\Quote\Model\Quote;

/**
 * Plugin class for {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation}
 * Used to add support for Aheadworks Giftcard
 */
class DiscountCodeValidationPlugin extends AbstractPlugin
{
    /**
     * @var ThirdPartyModuleFactory that returns {@see \Aheadworks\Giftcard\Api\GiftcardRepositoryInterface}
     */
    private $aheadworksGiftcardRepositoryFactory;

    /**
     * @var ThirdPartyModuleFactory that returns {@see \Aheadworks\Giftcard\Api\GiftcardCartManagementInterface}
     */
    private $aheadworksGiftcardCartManagementFactory;

    /**
     * DiscountCodeValidation plugin constructor
     *
     * @param CommonModuleContext     $context used to provide common dependencies and preconditions
     * @param ThirdPartyModuleFactory $aheadworksGiftcardRepositoryFactory Aheadworks Giftcard repository factory
     * @param ThirdPartyModuleFactory $aheadworksGiftcardCartManagementFactory Aheadworks Cart management factory
     */
    public function __construct(
        CommonModuleContext $context,
        ThirdPartyModuleFactory $aheadworksGiftcardRepositoryFactory,
        ThirdPartyModuleFactory $aheadworksGiftcardCartManagementFactory
    ) {
        parent::__construct($context);
        $this->aheadworksGiftcardRepositoryFactory = $aheadworksGiftcardRepositoryFactory;
        $this->aheadworksGiftcardCartManagementFactory = $aheadworksGiftcardCartManagementFactory;
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::loadGiftCardData}
     * Attempts to load Aheadworks Giftcard and, if found, returns an instance of the Giftcard object
     * Otherwise returns the result of the original(plugged) method
     *
     * @param DiscountCodeValidation $subject plugged Bolt Discount Code Validation object
     * @param callable               $proceed original method (or next plugin in line) reference
     * @param string                 $code potential giftcard code
     * @param int                    $websiteId quote website id
     *
     * @return GiftcardInterface|Giftcardaccount|null either Aheadworks giftcard or the result of the original method
     */
    public function aroundLoadGiftCardData(DiscountCodeValidation $subject, callable $proceed, $code, $websiteId)
    {
        if ($this->shouldRun() && $this->aheadworksGiftcardRepositoryFactory->isAvailable()
            && $this->aheadworksGiftcardRepositoryFactory->isExists()) {
            try {
                /** @var GiftcardRepositoryInterface $aheadworksGiftcardRepository */
                $aheadworksGiftcardRepository = $this->aheadworksGiftcardRepositoryFactory->getInstance();
                return $aheadworksGiftcardRepository->getByCode($code, $websiteId);
            } catch (LocalizedException $e) {
                // Aheadworks Giftcard not found, proceed to original method call
            }
        }
        return $proceed($code, $websiteId);
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Model\Api\DiscountCodeValidation::applyingGiftCardCode}
     * Applies Aheadworks Giftcard to both immutable and parent quotes if provided with appropriate giftcard object
     *
     * @param DiscountCodeValidation  $subject Bolt dicount code validation model
     * @param callable                $proceed original (next in line) method call
     * @param string                  $code giftcard code to be applied
     * @param GiftcardInterface|mixed $giftCard object to be applied
     * @param Quote                   $immutableQuote to apply the giftcard to
     * @param Quote                   $parentQuote to apply the giftcard to
     *
     * @return array|false containing dicount code validation and application result or false on exception
     *
     * @throws Exception if unable to send error response
     */
    public function aroundApplyingGiftCardCode(
        DiscountCodeValidation $subject,
        callable $proceed,
        $code,
        $giftCard,
        $immutableQuote,
        $parentQuote
    ) {
        if ($this->shouldRun() && $this->aheadworksGiftcardCartManagementFactory->isAvailable()
            && $this->aheadworksGiftcardCartManagementFactory->isExists()
            && $giftCard instanceof GiftcardInterface
        ) {
            try {
                /** @var GiftcardCartManagementInterface $giftcardCartManagement */
                $giftcardCartManagement = $this->aheadworksGiftcardCartManagementFactory->getInstance();
                try {
                    // on subsequent validation calls from Bolt checkout
                    // try removing the gift card before adding it
                    $giftcardCartManagement->remove($parentQuote->getId(), $giftCard->getCode(), false);
                } catch (Exception $e) {
                    // gift card not yet added
                }
                $giftcardCartManagement->set($parentQuote->getId(), $giftCard->getCode(), false);

                $result = [
                    'status'          => 'success',
                    'discount_code'   => $code,
                    'discount_amount' => abs(
                        CurrencyUtils::toMinor($giftCard->getBalance(), $parentQuote->getQuoteCurrencyCode())
                    ),
                    'description'     => __('Gift Card (%1)', $giftCard->getCode()),
                    'discount_type'   => 'fixed_amount',
                ];
                $this->logHelper->addInfoLog('### Gift Card Result');
                $this->logHelper->addInfoLog(json_encode($result));

                return $result;
            } catch (Exception $e) {
                $this->bugsnagHelper->notifyException($e);
                $subject->sendErrorResponse(
                    ErrorResponse::ERR_SERVICE,
                    $e->getMessage(),
                    422,
                    $immutableQuote
                );

                return false;
            }
        }
        return $proceed($code, $giftCard, $immutableQuote, $parentQuote);
    }
}
