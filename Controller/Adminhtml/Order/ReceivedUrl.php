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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

/**
 * Class ReceivedUrl
 *
 * @package Bolt\Boltpay\Controller\Adminhtml\Order
 */
class ReceivedUrl extends Action
{
    /**
     * @var LogHelper
     */
    private $logHelper;
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var Bugsnag
     */
    private $bugsnag;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var CartHelper
     */
    private $cartHelper;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * ReceivedUrl constructor.
     *
     * @param Context         $context
     * @param JsonFactory     $resultJsonFactory
     * @param ConfigHelper    $configHelper
     * @param CartHelper      $cartHelper
     * @param Bugsnag         $bugsnag
     * @param LogHelper       $logHelper
     * @param UrlInterface    $url
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        configHelper $configHelper,
        CartHelper $cartHelper,
        Bugsnag $bugsnag,
        LogHelper $logHelper,
        UrlInterface $url,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configHelper = $configHelper;
        $this->cartHelper = $cartHelper;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->url = $url;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $boltSignature = $this->getRequest()->getParam('bolt_signature');
        $boltPayload = $this->getRequest()->getParam('bolt_payload');

        $signature = base64_decode($boltSignature);

        $magentoSavedSignature = $this->configHelper->getSigningSecret();

        $hashBoltPayloadWithKey = hash_hmac('sha256', $boltPayload, $magentoSavedSignature, true);
        $hash = base64_encode($hashBoltPayloadWithKey);

        if ($signature === $hash) {
            // Seems that hook from Bolt.
//            $redirectUrl = $this->configHelper->getSuccessPageRedirect();

            try {
                $payload = base64_decode($boltPayload);
                $incrementId = $this->getIncrementIdFromPayload($payload);

                /** @var Order $order */
                $order = $this->getOrderByIncrementId($incrementId);

                if ($order->getState() !== Order::STATE_NEW) {
                    $message = __('An order have wrong state: %state', ['state' => $order->getState()]);
                    throw new LocalizedException($message);
                }

                /** @var Quote $quote */
                $quote = $this->getQuoteById($order->getQuoteId());

                $redirectUrl = $this->_url->getUrl('sales/order/view/', ['order_id' => $order->getId()]);

                // clear the session data
                if ($order->getId()) {
                    // add quote information to the session
                    $this->clearQuoteSession($quote);

                    // add order information to the session
                    $this->clearOrderSession($order);
                }

                $this->_redirect($redirectUrl);
            } catch (NoSuchEntityException $noSuchEntityException) {
                $logMessage = $noSuchEntityException->getMessage();
                $this->logHelper->addInfoLog('NoSuchEntityException: ' . $logMessage);

                $this->bugsnag->registerCallback(function ($report) use ($incrementId) {
                    $report->setMetaData([
                        'order incrementId' => $incrementId,
                    ]);
                });
                $this->bugsnag->notifyError('NoSuchEntityException: ', $logMessage);

                $errorMessage = __('Something went wrong. Please contact the seller.');
                $this->messageManager->addErrorMessage($errorMessage);

                $this->_redirect('/');
            } catch (LocalizedException $e) {
                $logMessage = $e->getMessage();
                $this->logHelper->addInfoLog('LocalizedException:' . $logMessage);

                $errorMessage = __('Something went wrong. Please contact the seller.');
                $this->messageManager->addErrorMessage($errorMessage);

                $this->bugsnag->registerCallback(function ($report) use ($boltSignature, $boltPayload) {
                    $report->setMetaData([
                        'bolt_signature' => $boltSignature,
                        'bolt_payload'   => $boltPayload,
                    ]);
                });
                $this->bugsnag->notifyError('LocalizedException: ', $logMessage);
                $this->_redirect('/');
            }
        } else {
            // Potentially it is attack.
            $logMessage = 'bolt_signature and Magento signature are not equal';
            $this->logHelper->addInfoLog($logMessage);

            $this->bugsnag->registerCallback(function ($report) use ($boltSignature, $boltPayload) {
                $report->setMetaData([
                    'bolt_signature' => $boltSignature,
                    'bolt_payload'   => $boltPayload
                ]);
            });
            $this->bugsnag->notifyError('OrderReceivedUrl Error', $logMessage);

            $errorMessage = __('Something went wrong. Please contact the seller.');
            $this->messageManager->addErrorMessage($errorMessage);
            $this->_redirect('/');
        }
    }

    /**
     * @param $payload
     * @return mixed
     */
    public function getIncrementIdFromPayload($payload)
    {
        $payloadArray = json_decode($payload, true);
        $displayId = $payloadArray['display_id'];

        list($incrementId, $quoteId) = array_pad(
            explode(' / ', $displayId),
            2,
            null
        );

        return $incrementId;
    }

    /**
     * @param $incrementId
     * @return Order
     * @throws NoSuchEntityException
     */
    public function getOrderByIncrementId($incrementId)
    {
        $order = $this->cartHelper->getOrderByIncrementId($incrementId);

        if (!$order) {
            throw new NoSuchEntityException(
                __('Could not find the order data.')
            );
        }

        return $order;
    }

    /**
     * @param $quoteId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteById($quoteId)
    {
        return $this->cartHelper->getQuoteById($quoteId);
    }

    /**
     * Clear quote session after successful order
     *
     * @param Quote
     *
     * @return void
     */
    private function clearQuoteSession($quote)
    {
        $this->checkoutSession->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();
    }

    /**
     * Clear order session after successful order
     *
     * @param Order
     *
     * @return void
     */
    private function clearOrderSession($order)
    {
        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
    }
}