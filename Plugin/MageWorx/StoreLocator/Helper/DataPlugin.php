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
 * @copyright  Copyright (c) 2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\MageWorx\StoreLocator\Helper;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\State;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Plugin for {@see \MageWorx\StoreLocator\Helper\Data}
 */
class DataPlugin
{
    /**
     * @var Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;
    
    /**
     * @param Bolt\Boltpay\Helper\Bugsnag $bugsnagHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        Session $checkoutSession,
        State $appState,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->checkoutSession = $checkoutSession;
        $this->appState = $appState;
        $this->quoteRepository = $quoteRepository;
    }
    
    /**
     *
     * @param \MageWorx\StoreLocator\Helper\Data $subject
     * @param \Magento\Quote\Model\Quote $result
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function afterGetQuote(
        \MageWorx\StoreLocator\Helper\Data $subject,
        \Magento\Quote\Model\Quote $result
    ) {
        if ($this->appState->getAreaCode() !== \Magento\Framework\App\Area::AREA_WEBAPI_REST) {
            return $result;
        }
        try {
            $mageWorxPickupQuoteId = $this->checkoutSession->getMageWorxPickupQuoteId();
            if ($mageWorxPickupQuoteId) {
                $quote = $this->quoteRepository->getActive($mageWorxPickupQuoteId);
                if ($quote) {
                    $result = $quote;
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }

        return $result;
    }
}
