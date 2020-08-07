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

namespace Bolt\Boltpay\Controller;

use Bolt\Boltpay\Helper\ArrayHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Bolt\Boltpay\Helper\Order as OrderHelper;

trait ReceivedUrlTrait
{
    /**
     * @var LogHelper
     */
    private $logHelper;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var Bugsnag
     */
    private $bugsnag;
    /**
     * @var CartHelper
     */
    private $cartHelper;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $boltSignature = $this->getRequest()->getParam('bolt_signature');
        $boltPayload = $this->getRequest()->getParam('bolt_payload');
        $storeId = $this->getRequest()->getParam('store_id');

        // phpcs:ignore
        $signature = base64_decode($boltSignature);

        $signingSecret = $this->configHelper->getSigningSecret($storeId);

        $hashBoltPayloadWithKey = hash_hmac('sha256', $boltPayload, $signingSecret, true);
        $hash = base64_encode($hashBoltPayloadWithKey);

        if ($signature === $hash) {
            try {
                // phpcs:ignore
                $payload = base64_decode($boltPayload);
                $payloadArray = json_decode($payload, true);
                $incrementId = $this->getIncrementIdFromPayload($payloadArray);

                /** @var Order $order */
                $order = $this->getOrderByIncrementId($incrementId);

                /** @var Quote $quote */
                $quote = $this->getQuoteById($order->getQuoteId());

                if ($this->redirectToAdminIfNeeded($quote)) {
                    // redirected
                    return;
                }

                if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                    // Save reference to the Bolt transaction with the order
                    $order->addStatusHistoryComment(
                        __(
                            'Bolt transaction: %1',
                            $this->orderHelper->formatReferenceUrl($this->getReferenceFromPayload($payloadArray))
                        )
                    )->save();
                } else {
                    $this->bugsnag->notifyError(
                        "Pre-Auth redirect wrong order state",
                        "OrderNo: $incrementId, State: {$order->getState()}"
                    );
                }

                $this->orderHelper->dispatchPostCheckoutEvents($order, $quote);

                $redirectUrl = $this->getRedirectUrl($order);

                // add quote information to the session
                $this->clearQuoteSession($quote);

                // add order information to the session
                $this->clearOrderSession($order, $redirectUrl);

                $this->_redirect($redirectUrl);
            } catch (NoSuchEntityException $noSuchEntityException) {
                $logMessage = $noSuchEntityException->getMessage();
                $this->logHelper->addInfoLog('NoSuchEntityException: ' . $logMessage);

                $this->bugsnag->registerCallback(function ($report) use ($incrementId, $storeId) {
                    $report->setMetaData([
                        'order_id' => $incrementId,
                        'store_id'    => $storeId
                    ]);
                });
                $this->bugsnag->notifyError('NoSuchEntityException: ', $logMessage);

                $errorMessage = __('Something went wrong. Please contact the seller.');
                $this->messageManager->addErrorMessage($errorMessage);

                $this->_redirect($this->getErrorRedirectUrl());
            } catch (LocalizedException $e) {
                $logMessage = $e->getMessage();
                $this->logHelper->addInfoLog('LocalizedException:' . $logMessage);

                $errorMessage = __('Something went wrong. Please contact the seller.');
                $this->messageManager->addErrorMessage($errorMessage);

                $this->bugsnag->registerCallback(function ($report) use ($boltSignature, $boltPayload, $storeId) {
                    $report->setMetaData([
                        'bolt_signature' => $boltSignature,
                        'bolt_payload'   => $boltPayload,
                        'store_id' => $storeId
                    ]);
                });
                $this->bugsnag->notifyError('LocalizedException: ', $logMessage);
                $this->_redirect($this->getErrorRedirectUrl());
            }
        } else {
            // Potentially it is attack.
            $logMessage = 'bolt_signature and Magento signature are not equal';
            $this->logHelper->addInfoLog($logMessage);

            $this->bugsnag->registerCallback(function ($report) use ($boltSignature, $boltPayload, $storeId) {
                $report->setMetaData([
                    'bolt_signature' => $boltSignature,
                    'bolt_payload'   => $boltPayload,
                    'store_id' => $storeId
                ]);
            });
            $this->bugsnag->notifyError('OrderReceivedUrl Error', $logMessage);

            $errorMessage = __('Something went wrong. Please contact the seller.');
            $this->messageManager->addErrorMessage($errorMessage);
            $this->_redirect($this->getErrorRedirectUrl());
        }
    }

    /**
     * @param $payload
     * @return mixed
     */
    private function getReferenceFromPayload($payload)
    {
        return ArrayHelper::getValueFromArray($payload, 'transaction_reference', '');
    }

    /**
     * @param $payload
     * @return string
     */
    private function getIncrementIdFromPayload($payload)
    {
        $displayId = ArrayHelper::getValueFromArray($payload, 'display_id', '');
        $data = ArrayHelper::extractDataFromDisplayId($displayId);

        return isset($data[0]) ? $data[0] : '';
    }

    /**
     * @param $incrementId
     * @return Order
     * @throws NoSuchEntityException
     */
    private function getOrderByIncrementId($incrementId)
    {
        $order = $this->orderHelper->getExistingOrder($incrementId);

        if (!$order) {
            throw new NoSuchEntityException(
                __('Could not find the order data.')
            );
        }

        return $order;
    }

    /**
     * @param $quoteId
     * @return false|Quote
     */
    private function getQuoteById($quoteId)
    {
        return $this->cartHelper->getQuoteById($quoteId);
    }

    /**
     * Clear quote session after successful order
     *
     * @param Quote $quote
     *
     * @return void
     */
    private function clearQuoteSession($quote)
    {
        $this->checkoutSession
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();
    }

    /**
     * Clear order session after successful order
     *
     * @param Order $order
     * @param string $redirectUrl
     *
     * @return void
     */
    private function clearOrderSession($order, $redirectUrl)
    {
        $this->checkoutSession
            ->setLastOrderId($order->getId())
            ->setRedirectUrl($redirectUrl)
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
    }
}
