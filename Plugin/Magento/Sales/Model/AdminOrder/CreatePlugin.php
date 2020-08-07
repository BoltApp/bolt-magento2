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

namespace Bolt\Boltpay\Plugin\Magento\Sales\Model\AdminOrder;

use Magento\Sales\Model\AdminOrder\Create;
use Magento\Backend\Model\Session\Quote as AdminCheckoutSession;
use Bolt\Boltpay\Helper\Config as ConfigHelper;

class CreatePlugin
{
    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    private $configHelper;

    /** @var AdminCheckoutSession */
    private $adminCheckoutSession;

    /**
     * CreatePlugin constructor.
     * @param ConfigHelper $configHelper
     * @param AdminCheckoutSession $adminCheckoutSession
     */
    public function __construct(
        ConfigHelper $configHelper,
        AdminCheckoutSession $adminCheckoutSession
    ) {
        $this->adminCheckoutSession = $adminCheckoutSession;
        $this->configHelper = $configHelper;
    }

    /**
     * @param Create $subject
     * @param $data
     * @return array
     */
    public function beforeImportPostData(Create $subject, $data)
    {
        if ($this->configHelper->isStorePickupFeatureEnabled() && isset($data['shipping_method'])) {
            if ($this->configHelper->isPickupInStoreShippingMethodCode($data['shipping_method'])) {
                $this->adminCheckoutSession->setData('old_shipping_address', $subject->getShippingAddress()->getData());
                $subject->getShippingAddress()->addData($this->configHelper->getPickupAddressData());
            } else {
                if ($oldShippingAddress = $this->adminCheckoutSession->getData('old_shipping_address')) {
                    $subject->getShippingAddress()->addData($oldShippingAddress);
                    $this->adminCheckoutSession->unsetData('old_shipping_address');
                }
            }
        }

        return [$data];
    }
}
