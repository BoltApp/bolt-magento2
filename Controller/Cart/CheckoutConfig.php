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

namespace Bolt\Boltpay\Controller\Cart;

/**
 * Class CheckoutConfig
 * Retrieve checkout config consumed by minicart addons {@see \Bolt\Boltpay\ViewModel\MinicartAddons}
 */
class CheckoutConfig extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Checkout\Model\Session current checkout session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Checkout\Model\CompositeConfigProvider default checkout configuration provider
     */
    private $configProvider;

    /**
     * CheckoutConfig action constructor
     * @param \Magento\Framework\App\Action\Context           $context default Action context
     * @param \Magento\Checkout\Model\Session                 $checkoutSession current checkout session
     * @param \Magento\Checkout\Model\CompositeConfigProvider $configProvider default checkout configuration provider
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Model\CompositeConfigProvider $configProvider
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->configProvider = $configProvider;
    }

    /**
     * Generates checkout config that is otherwise present as window.checkoutConfig on checkout and cart pages
     *
     * @return \Magento\Framework\Controller\Result\Json response object that will encode data into JSON format string
     *                                                   and set content type header to application/json
     */
    public function execute()
    {
        $config = $this->checkoutSession->getQuoteId() ? $this->configProvider->getConfig() : [];
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData($config);
    }
}
