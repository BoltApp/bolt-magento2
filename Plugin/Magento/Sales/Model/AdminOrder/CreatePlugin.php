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

namespace Bolt\Boltpay\Plugin\Magento\Sales\Model\AdminOrder;

use Magento\Sales\Model\AdminOrder\Create;


class CreatePlugin
{
    /**
     * @var \Bolt\Boltpay\Helper\Config
     */
    private $configHelper;

    /**
     * CreatePlugin constructor.
     * @param \Bolt\Boltpay\Helper\Config $configHelper
     */
    public function __construct(
        \Bolt\Boltpay\Helper\Config $configHelper
    )
    {
        $this->configHelper = $configHelper;
    }

    public function aroundImportPostData(Create $subject, callable $proceed, $data)
    {
        if (isset($data['shipping_method'])) {
            if ($this->configHelper->isPickupInStoreShippingRateCode($data['shipping_method'])) {
                $_SESSION['old_shipping_address'] = $subject->getShippingAddress()->getData();
                $subject->getShippingAddress()->addData($this->configHelper->getPickupAddressData());
            } else {
                if (@$_SESSION['old_shipping_address']) {
                    $subject->getShippingAddress()->addData($_SESSION['old_shipping_address']);
                    unset($_SESSION['old_shipping_address']);
                }
            }
        }

        $subject = $proceed($data);

        return $subject;
    }
}
