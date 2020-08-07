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
 
namespace Bolt\Boltpay\Observer\Adminhtml\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class OrderCreateProcessDataObserver
 *
 * @package Bolt\Boltpay\Observer\Adminhtml\Sales
 */
class OrderCreateProcessDataObserver implements ObserverInterface
{
    private $productMetadata;

    public function __construct(
        ProductMetadataInterface $productMetadata
    ) {
        $this->productMetadata = $productMetadata;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $magentoVersion = $this->productMetadata->getVersion() ;
        if (version_compare($magentoVersion, '2.1.4') < 0) {
            $event = $observer->getEvent();
            $orderCreateModel = $event->getData('order_create_model');
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $orderCreateModel->getQuote();

            if ($account = $orderCreateModel->getData('account')) {
                if (isset($account['email']) && !empty($account['email'])) {
                    $quote->setCustomerEmail($account['email']);
                }
            }
        }
    }
}
