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
namespace Bolt\Boltpay\Block\Checkout;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\ProductMetadataInterface;
// Customization
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\OrderFactory as OrderFactory;
use Magestore\Storepickup\Model\ResourceModel\Store\CollectionFactory as StorepickupStoreCollection;

/**
 * Class Success
 *
 * @package Bolt\Boltpay\Block\Checkout
 */
class Success extends Template
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var Config
     */
    private $configHelper;
    
    /* Customization Start */
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    
    /**
     * @var StorepickupStoreCollection
     */
    private $storepickupStoreCollection;
    /* Customization End */

    /**
     * Success constructor.
     *
     * @param ProductMetadataInterface   $productMetadata
     * @param Config                     $configHelper
     * @param CheckoutSession            $checkoutSession
     * @param OrderFactory               $orderFactory
     * @param StorepickupStoreCollection $storepickupStoreCollection
     * @param Context                    $context
     * @param array                      $data
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        Config $configHelper,
        CheckoutSession $checkoutSession,
        OrderFactory $orderFactory,
        StorepickupStoreCollection $storepickupStoreCollection,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->productMetadata = $productMetadata;
        $this->configHelper = $configHelper;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->storepickupStoreCollection = $storepickupStoreCollection;
    }

    /**
     * @return bool
     */
    public function isAllowInvalidateQuote()
    {
        // Workaround for known magento issue - https://github.com/magento/magento2/issues/12504
        return (bool) (version_compare($this->getMagentoVersion(), '2.2.0', '<'))
            || (bool) (version_compare($this->getMagentoVersion(), '2.3.4', '>='));
    }

    /**
     * @return bool
     */
    public function shouldTrackCheckoutFunnel() {
        return $this->configHelper->shouldTrackCheckoutFunnel();
    }

    /**
     * Get magento version
     *
     * @return string
     */
    private function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }
    
    /* Customization Start */
    public function getOrder()
    {
        return  $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
    }
    
    public function getStorepickupSession()
    {
        return $this->checkoutSession->getData('storepickup_session');
    }
    
    public function getStorepickupDetails()
    {
        $order = $this->getOrder();
        $pickupId = $order->getData('storepickup_id') ?: "";
        $collection = $this->getStoreCollection();
        $collection->addFieldToFilter('status','1')->addFieldToSelect(['storepickup_id', 'store_name','address','city','country_id','description','phone','latitude','longitude']);
        $collection->setOrder('store_name', \Magento\Framework\Data\Collection\AbstractDb::SORT_ORDER_ASC);
        $liststore = $collection->getData();
        foreach($liststore as $key=>$store){
            if($store['storepickup_id'] == $pickupId){
                return $store;
            }
        }
        return '';
    }
    
    public function getStoreCollection()
    {
        return $this->storepickupStoreCollection->create();
    }
    /* Customization End */
}
