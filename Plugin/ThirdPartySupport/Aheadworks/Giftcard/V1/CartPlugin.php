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

use Aheadworks\Giftcard\Api\GiftcardCartManagementInterface;
use Bolt\Boltpay\Helper\Cart;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Bolt\Boltpay\Model\ThirdPartyModuleFactory;
use Bolt\Boltpay\Plugin\ThirdPartySupport\AbstractPlugin;
use Bolt\Boltpay\Plugin\ThirdPartySupport\CommonModuleContext;
use Exception;

/**
 * Plugin class for {@see \Bolt\Boltpay\Helper\Cart}
 * Used to add support for Aheadworks Giftcard
 */
class CartPlugin extends AbstractPlugin
{
    /**
     * @var GiftcardCartManagementInterface Aheadworks Giftcard management object factory
     */
    private $giftcardCartManagementFactory;

    /**
     * Cart plugin constructor
     *
     * @param CommonModuleContext     $context used to provide common dependencies and preconditions
     * @param ThirdPartyModuleFactory $giftcardCartManagementFactory Aheadworks Giftcard management object factory
     */
    public function __construct(
        CommonModuleContext $context,
        ThirdPartyModuleFactory $giftcardCartManagementFactory
    ) {
        parent::__construct($context);
        $this->giftcardCartManagementFactory = $giftcardCartManagementFactory;
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Helper\Cart::collectDiscounts} method
     * Adds Aheadworks Giftcards to discounts collected by Bolt
     *
     * @param Cart  $subject original Cart Helper instance
     * @param array $result of the plugged method
     *
     * @return array original result appended with Aheadworks Giftcards
     */
    public function afterCollectDiscounts(Cart $subject, $result)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        if ($this->shouldRun() && $this->giftcardCartManagementFactory->isAvailable()
            && $this->giftcardCartManagementFactory->isExists()
        ) {
            try {
                $immutableQuote = $subject->getLastImmutableQuote();
                $parentQuoteId = $immutableQuote->getData('bolt_parent_quote_id');
                $currencyCode = $immutableQuote->getQuoteCurrencyCode();
                /** @var GiftcardCartManagementInterface $aheadworksGiftcardManagement */
                $aheadworksGiftcardManagement = $this->giftcardCartManagementFactory->getInstance();
                foreach ($aheadworksGiftcardManagement->get($parentQuoteId, false) as $giftcardQuote) {
                    $discounts[] = [
                        'description' => "Gift Card ({$giftcardQuote->getGiftcardCode()})",
                        'amount'      => CurrencyUtils::toMinor($giftcardQuote->getGiftcardBalance(), $currencyCode),
                        'type'        => 'fixed_amount'
                    ];
                    $totalAmount -= CurrencyUtils::toMinor($giftcardQuote->getGiftcardAmount(), $currencyCode);
                }
            } catch (Exception $e) {
                $this->bugsnagHelper->notifyException($e);
            }
        }
        return [$discounts, $totalAmount, $diff];
    }
}
