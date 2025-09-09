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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\ShipmentEstimationInterface as BoltShipmentEstimationInterface;
use Bolt\Boltpay\Api\Data\ShippingMethodWithTotalsInterfaceFactory as BoltShippingMethodWithTotalsFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Api\Data\AddressInterface;
use \Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Bolt\Boltpay\Helper\Bugsnag;

class ShipmentEstimation implements BoltShipmentEstimationInterface
{
    /**
     * @var ShipmentEstimationInterface
     */
    private $shipmentEstimation;

    /**
     * @var ShippingInformationManagementInterface
     */
    private $shippingInformationManagement;

    /**
     * @var ShippingInformationInterfaceFactory
     */
    private $shippingInformationFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var BoltShippingMethodWithTotalsFactory
     */
    private $boltShippingMethodWithTotalsFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param CartRepositoryInterface $cartRepository
     * @param BoltShippingMethodWithTotalsFactory $boltShippingMethodWithTotalsFactory
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ShipmentEstimationInterface $shipmentEstimation,
        ShippingInformationManagementInterface $shippingInformationManagement,
        ShippingInformationInterfaceFactory $shippingInformationFactory,
        CartRepositoryInterface $cartRepository,
        BoltShippingMethodWithTotalsFactory $boltShippingMethodWithTotalsFactory,
        Bugsnag $bugsnag
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->cartRepository = $cartRepository;
        $this->boltShippingMethodWithTotalsFactory = $boltShippingMethodWithTotalsFactory;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @param $cartId
     * @param AddressInterface $address
     * @return array|\Bolt\Boltpay\Api\Data\ShippingMethodWithTotalsInterface[]
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function estimateMethodsWithTaxesByExtendedAddress($cartId, AddressInterface $address)
    {
        $snapshot = $this->makeQuoteSnapshot($cartId);
        try {
            $shippingMethods = $this->shipmentEstimation->estimateByExtendedAddress($cartId, $address);
            $boltShippingMethods = [];
            foreach ($shippingMethods as $shippingMethod) {
                $shippingInformation = $this->shippingInformationFactory->create();
                $shippingInformation->setShippingAddress($address)
                    ->setShippingMethodCode($shippingMethod->getMethodCode())
                    ->setShippingCarrierCode($shippingMethod->getCarrierCode());

                $totals = $this->shippingInformationManagement
                    ->saveAddressInformation($cartId, $shippingInformation)
                    ->getTotals();

                // replace shipping incl tax with correct value from totals, because default magento shipping method
                // doesn't contain correct tax amount in shipping_incl_tax field !
                $shippingMethod->setPriceInclTax($totals->getShippingInclTax());

                /** @var \Bolt\Boltpay\Api\Data\ShippingMethodWithTotalsInterface $boltShippingMethodWithTotals */
                $boltShippingMethodWithTotals = $this->boltShippingMethodWithTotalsFactory->create();
                $boltShippingMethodWithTotals->setTotals($totals);
                $boltShippingMethodWithTotals->setShippingMethod($shippingMethod);
                $boltShippingMethods[] = $boltShippingMethodWithTotals;
            }
            return $boltShippingMethods;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } finally {
            $this->rollbackQuote($cartId, $snapshot);
        }

    }

    /**
     * Make snapshot of current quote state
     *
     * @param $cartId
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function makeQuoteSnapshot($cartId)
    {
        $quote = $this->cartRepository->getActive((int)$cartId);
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();

        return [
            'shipping_address_data'  => $shippingAddress ? $shippingAddress->getData() : [],
            'billing_address_data'  => $billingAddress ? $billingAddress->getData() : [],
            'shipping_method' => $shippingAddress ? $shippingAddress->getShippingMethod() : null,
            'payment_method' => $quote->getPayment() ? $quote->getPayment()->getMethod() : null,
            'totals_collected_flag' => (bool)$quote->getTotalsCollectedFlag(),
        ];
    }

    /**
     * Rollback quote to previous state
     *
     * @param $cartId
     * @param array $snapshot
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function rollbackQuote($cartId, array $snapshot): void
    {
        $quote = $this->cartRepository->getActive((int)$cartId);
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();

        try {
            if ($shippingAddress) {
                $shippingAddressId = $shippingAddress->getId();
                $shippingAddress->setData($snapshot['shipping_address_data'] ?: []);
                $shippingAddress->setId($shippingAddressId);
                $shippingAddress->removeAllShippingRates();
                $shippingAddress->setCollectShippingRates(false);
                $shippingAddress->setShippingMethod($snapshot['shipping_method']);
            }

            if ($billingAddress) {
                $billingAddressId = $billingAddress->getId();
                $billingAddress->setData($snapshot['billing_address_data'] ?: []);
                $billingAddress->setId($billingAddressId);
            }

            if ($snapshot['payment_method']) {
                $quote->getPayment()->setMethod($snapshot['payment_method']);
            } else {
                if ($quote->getPayment()) {
                    $quote->getPayment()->unsMethod();
                }
            }

            $quote->setTotalsCollectedFlag($snapshot['totals_collected_flag']);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }
}
