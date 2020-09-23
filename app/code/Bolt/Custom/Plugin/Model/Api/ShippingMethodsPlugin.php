<?php

namespace Bolt\Custom\Plugin\Model\Api;

class ShippingMethodsPlugin extends BaseWebhookPlugin
{
    /**
     * Before plugin for {@see \Bolt\Boltpay\Model\Api\ShippingMethods::getShippingMethods}
     *
     *
     * @param \Bolt\Boltpay\Model\Api\ShippingMethods $subject
     * @param array                                   $cart
     * @param array                                   $shipping_address
     *
     * @return array unchanged parameters
     */
    public function beforeGetShippingMethods(\Bolt\Boltpay\Model\Api\ShippingMethods $subject, $cart, $shipping_address)
    {
        $this->bugsnag->notifyError(
            'Plugin executed',
            '\Bolt\Custom\Plugin\Model\Api\ShippingMethodsPlugin::beforeGetShippingMethods'
        );
        if (key_exists('metadata', $cart)) {
            $this->storeBoltMetadata($cart['metadata']);
        }
        return [$cart, $shipping_address];
    }
}