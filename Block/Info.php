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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block;

class Info extends \Magento\Payment\Block\Info
{
    protected $_template = 'Bolt_Boltpay::info/default.phtml';
    
    /**
     * @param null $transport
     * @return \Magento\Framework\DataObject|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $boltProcessor = $info->getAdditionalInformation('processor');
        $data = [];
        
        if (empty($boltProcessor) || $boltProcessor == \Bolt\Boltpay\Helper\Order::TP_VANTIV) {
            if ($ccType = $info->getCcType()) {
                $data[(string)__('Credit Card Type')] = strtoupper($ccType);
            }
    
            if ($ccLast4 = $info->getCcLast4()) {
                $data[(string)__('Credit Card Number')] = sprintf('xxxx-%s', $ccLast4);
            }
        }

        if ($data) {
            $transport->setData(array_merge($transport->getData(), $data));
        }

        return $transport;
    }
    
    public function displayPaymentMethodTitle()
    {
        $info = $this->getInfo();
        $boltProcessor = $info->getAdditionalInformation('processor');
        if (empty($boltProcessor) || $boltProcessor == \Bolt\Boltpay\Helper\Order::TP_VANTIV) {
            $paymentTitle = $this->getMethod()->getConfigData('title', $info->getOrder()->getStoreId());
        } else {
            $paymentTitle = array_key_exists($boltProcessor, \Bolt\Boltpay\Helper\Order::TP_METHOD_DISPLAY)
                ? 'Bolt-' . \Bolt\Boltpay\Helper\Order::TP_METHOD_DISPLAY[ $boltProcessor ]
                : 'Bolt-' . strtoupper($boltProcessor);
        }
        
        return $paymentTitle;
    }
}
