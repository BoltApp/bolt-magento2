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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Quote\Model;

use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Bolt\Boltpay\Helper\Cart;

/**
 * Payment method management plugin for supporting ShipperHQ custom admin shipping rate ability
 */
class PaymentMethodManagementPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Set skipping collect totals flag for quote with shipper hq custom admin shipping rate
     *
     * @param PaymentMethodManagementInterface $subject
     * @param $cartId
     * @param PaymentInterface $method
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeSet(
        PaymentMethodManagementInterface $subject,
        $cartId,
        PaymentInterface $method
    ) {
        $quote = $this->cartRepository->get($cartId);
        if ($quote->getShippingAddress()->getShippingMethod() === Cart::SHIPPER_HQ_ADMIN_SHIPPING_METHOD) {
            $quote->setData(Cart::SHIPPER_HQ_SKIP_QUOTE_COLLECT_TOTALS, true);
        }
        return [$cartId, $method];
    }
}
