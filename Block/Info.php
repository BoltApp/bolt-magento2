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
        
        if ( empty($boltProcessor) || $boltProcessor == \Bolt\Boltpay\Helper\Order::TP_VANTIV ) {
            if ($ccType = $info->getCcType()) {
                $data[(string)__('Credit Card Type')] = strtoupper($ccType);
            }
    
            if ($ccLast4 = $info->getCcLast4()) {
                $data[(string)__('Credit Card Number')] = sprintf('xxxx-%s', $ccLast4);
            }
        } else {
            $data[(string)__('Paid via')] = strtoupper($boltProcessor);
        }

        if ($data) {
            $transport->setData(array_merge($transport->getData(), $data));
        }

        return $transport;
    }
}
