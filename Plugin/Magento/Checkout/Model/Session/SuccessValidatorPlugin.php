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
namespace Bolt\Boltpay\Plugin\Magento\Checkout\Model\Session;

use Bolt\Boltpay\Helper\Session as BoltSession;
use Bolt\Boltpay\Controller\ReceivedUrlInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface as Serialize;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Plugin for {@see \Magento\Checkout\Model\Session\SuccessValidator}
 */
class SuccessValidatorPlugin
{
    /** @var CacheInterface */
    private $cache;
    
    /** @var Serialize */
    private $serialize;
    
    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\Serialize\SerializerInterface $serialize
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @codeCoverageIgnore
     */
    public function __construct(
        CacheInterface $cache,
        Serialize $serialize,
        CheckoutSession $checkoutSession
    ) {
        $this->cache = $cache;
        $this->serialize = $serialize;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Restore checkout session data from cache
     */
    public function beforeIsValid(\Magento\Checkout\Model\Session\SuccessValidator $subject)
    {
        $cacheIdentifier = ReceivedUrlInterface::BOLT_ORDER_SUCCESS_PREFIX . $this->checkoutSession->getSessionId();
        if ($serialized = $this->cache->load($cacheIdentifier)) {
            $sessionData = $this->serialize->unserialize($serialized);
            
            $this->checkoutSession
                ->setLastQuoteId($sessionData['LastQuoteId'])
                ->setLastSuccessQuoteId($sessionData['LastSuccessQuoteId'])
                ->clearHelperData();
            
            $this->checkoutSession
                ->setLastOrderId($sessionData['LastOrderId'])
                ->setRedirectUrl($sessionData['RedirectUrl'])
                ->setLastRealOrderId($sessionData['LastRealOrderId'])
                ->setLastOrderStatus($sessionData['LastOrderStatus']);
                
            $this->cache->remove($cacheIdentifier);
        }
        
        return null;
    }
}
