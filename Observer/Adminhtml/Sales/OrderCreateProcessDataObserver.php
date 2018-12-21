<?php
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
    )
    {
        $this->productMetadata = $productMetadata;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $magentoVersion = $this->productMetadata->getVersion() ;
        if (version_compare($magentoVersion, '2.1.4') < 0 ) {
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
