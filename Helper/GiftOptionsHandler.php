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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Boltpay web hook helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GiftOptionsHandler extends AbstractHelper
{
    const COMMENT_PREFIX_TEXT = 'BOLTPAY INFO :: gift options';
    
    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context $context
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Context $context,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->bugsnag = $bugsnag;
    }

    /**
     * Handle gift options
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \stdClass $transaction
     */
    public function handle($order, $transaction)
    {
        if (!isset($transaction->order->cart->shipments[0]->gift_options)) {
            return;
        }
        $giftOptions = $transaction->order->cart->shipments[0]->gift_options;
        $comment = '<br>Gift Wrap: ' . ($giftOptions->wrap ? 'Yes' : 'No');
        $comment .= '<br>Gift Message: ' . $giftOptions->message;
        $order->addCommentToStatusHistory(self::COMMENT_PREFIX_TEXT.$comment);
        $order->save();
    }
}
