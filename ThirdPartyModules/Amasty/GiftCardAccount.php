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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor;
use Amasty\GiftCardAccount\Model\OptionSource\AccountStatus;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;

class GiftCardAccount
{

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     */
    public function __construct(Bugsnag $bugsnagHelper)
    {
        $this->bugsnagHelper = $bugsnagHelper;
    }

    /**
     * Restores Amasty Giftcard balances used in an order that is going to be deleted
     *
     * @param \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository         $giftcardRepository
     * @param \Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository $giftcardOrderRepository
     * @param Order                                                            $order
     */
    public function beforeDeleteOrder($giftcardRepository, $giftcardOrderRepository, $order)
    {
        try {
            $giftcardOrderExtension = $giftcardOrderRepository->getByOrderId($order->getId());
            foreach ($giftcardOrderExtension->getGiftCards() as $orderGiftcard) {
                try {
                    /** @see GiftCardCartProcessor::GIFT_CARD_ID */
                    $giftcard = $giftcardRepository->getById($orderGiftcard['id']);
                    $giftcard->setCurrentValue(
                        /** @see GiftCardCartProcessor::GIFT_CARD_BASE_AMOUNT */
                        (float)($giftcard->getCurrentValue() + $orderGiftcard['b_amount'])
                    );
                    /** @see \Amasty\GiftCardAccount\Model\OptionSource\AccountStatus::STATUS_ACTIVE */
                    $giftcard->setStatus(1);
                    $giftcardRepository->save($giftcard);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->bugsnagHelper->notifyException($e);
                }
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //no giftcards applied on order, safe to ignore
        }
    }
}
