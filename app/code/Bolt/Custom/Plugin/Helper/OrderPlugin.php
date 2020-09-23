<?php

namespace Bolt\Custom\Plugin\Helper;

/**
 * Plugin for {@see \Bolt\Boltpay\Helper\Order}
 */
class OrderPlugin extends \Bolt\Custom\Plugin\Model\Api\BaseWebhookPlugin
{
    /**
     * Plugin for {@see \Bolt\Boltpay\Helper\Order::prepareQuote}
     * Used to restore session data from transaction metadata
     *
     * @param \Bolt\Boltpay\Helper\Order $subject Bolt order helper
     * @param \Magento\Quote\Model\Quote $immutableQuote
     * @param \stdClass                  $transaction
     *
     * @return array containing unchanged method call parameters
     */
    public function beforePrepareQuote(\Bolt\Boltpay\Helper\Order $subject, $immutableQuote, $transaction)
    {
        //convert stdClass object to array
        $txArray = \json_decode(\json_encode($transaction), true);
        $this->bugsnag->notifyError('Plugin executed', '\Bolt\Custom\Plugin\Helper\OrderPlugin::beforePrepareQuote');
        if (key_exists('order', $txArray)
            && key_exists('cart', $txArray['order'])
            && key_exists('metadata', $txArray['order']['cart'])
        ) {
            $this->storeBoltMetadata($txArray['order']['cart']['metadata']);
        }

        return [$immutableQuote, $transaction];
    }
}