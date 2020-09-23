<?php

namespace Bolt\Custom\Plugin\Helper;

use Aheadworks\Giftcard\Api\GiftcardCartManagementInterface;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

/**
 * Plugin for {@see \Bolt\Boltpay\Helper\Cart}
 * Adds support for Aheadworks_Giftcard discount
 */
class CartPlugin
{
    /**
     * @var GiftcardCartManagementInterface used to retrieve giftcards for a quote
     */
    private $giftcardCartManagement;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag used to record exceptions
     */
    private $bugsnag;

    /**
     * @var \Bolt\Boltpay\Helper\Log used to write to the bolt.log file
     */
    private $logHelper;

    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $session;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    private $encryptor;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * CartPlugin constructor.
     * @param GiftcardCartManagementInterface                    $giftcardCartManagement used to retrieve giftcards for a quote
     * @param \Bolt\Boltpay\Helper\Bugsnag                       $bugsnag used to record exceptions
     * @param \Bolt\Boltpay\Helper\Log                           $logHelper used to write to the bolt.log file
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param \Magento\Customer\Model\Session                    $customerSession
     * @param \Magento\Framework\Encryption\Encryptor            $encryptor
     */
    public function __construct(
        GiftcardCartManagementInterface $giftcardCartManagement,
        \Bolt\Boltpay\Helper\Bugsnag $bugsnag,
        \Bolt\Boltpay\Helper\Log $logHelper,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Encryption\Encryptor $encryptor
    ) {
        $this->giftcardCartManagement = $giftcardCartManagement;
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
        $this->session = $session;
        $this->encryptor = $encryptor;
        $this->customerSession = $customerSession;
    }

    /**
     * Plugin for {@see \Bolt\Boltpay\Helper\Cart::collectDiscounts} method
     * Adds Aheadworks Giftcards to discounts collected by Bolt
     *
     * @param \Bolt\Boltpay\Helper\Cart $subject
     * @param array                     $result of the wrapped method
     * @return array original result appended with Aheadworks Giftcards
     */
    public function afterCollectDiscounts(\Bolt\Boltpay\Helper\Cart $subject, $result)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            // Call protected method with a Closure proxy
            $methodCaller = function ($methodName, ...$params) {
                return $this->$methodName(...$params);
            };
            $immutableQuote = $methodCaller->call($subject, 'getLastImmutableQuote');
            $parentQuoteId = $immutableQuote->getData('bolt_parent_quote_id');
            $currencyCode = $immutableQuote->getQuoteCurrencyCode();
            foreach ($this->giftcardCartManagement->get($parentQuoteId, false) as $giftcard) {
                $discounts[] = [
                    'description' => "Gift Card ({$giftcard->getGiftcardCode()})",
                    'amount'      => CurrencyUtils::toMinor($giftcard->getGiftcardBalance(), $currencyCode),
                    'type'        => 'fixed_amount'
                ];
                $totalAmount -= CurrencyUtils::toMinor($giftcard->getGiftcardAmount(), $currencyCode);
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logHelper->addInfoLog($e->getMessage());
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
        return [$discounts, $totalAmount, $diff];
    }

    /**
     * After plugin for {@see \Bolt\Boltpay\Helper\Cart::getCartData}
     *
     * @param \Bolt\Boltpay\Helper\Cart $subject
     * @param array                     $result of the original method call
     *
     * @return array
     */
    public function afterGetCartData(\Bolt\Boltpay\Helper\Cart $subject, $result)
    {
        if (is_array($result) && !empty($result)) {
            $session['type'] = $this->session instanceof \Magento\Backend\Model\Session ? "admin" : "frontend";
            $session['encrypted_id'] = $this->encryptor->encrypt($this->session->getSessionId());
            $session['customer_data']['idme_group'] = $this->customerSession->getData('idme_group');
            $session['customer_data']['idme_subgroups'] = $this->customerSession->getData('idme_subgroups');
            $session['customer_data']['idme_uuid'] = $this->customerSession->getData('idme_uuid');
            $result['metadata']['session'] = \json_encode($session);
        }
        return $result;
    }
}