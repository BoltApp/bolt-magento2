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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * Boltpay web hook helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckboxesHandler extends AbstractHelper
{
    const CATEGORY_NEWSLETTER = 'NEWSLETTER';
    const COMMENT_PREFIX_TEXT = 'BOLTPAY INFO :: checkboxes';

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context $context
     * @param OrderHelper $orderHelper
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Context $context,
        Bugsnag $bugsnag,
        SubscriberFactory $subscriberFactory
    )
    {
        parent::__construct($context);
        $this->subscriberFactory = $subscriberFactory;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Handle checkboxes
     *
     * @param OrderModel $order
     * @param array $checkboxes
     */
    public function handle($order, $checkboxes)
    {
        $comment = '';
        $needSubscribe = false;
        foreach ($checkboxes as $checkbox) {
            if ($checkbox['category'] == self::CATEGORY_NEWSLETTER && $checkbox['value']) {
                $needSubscribe = true;
            }
            $comment .= '<br>' . $checkbox['text'] . ': ' . ($checkbox['value'] ? 'Yes' : 'No');
        }
        if ($comment) {
            $order->addCommentToStatusHistory(self::COMMENT_PREFIX_TEXT.$comment);
            $order->save();
        }
        if ($needSubscribe) {
            $this->subscribeForNewsletter($order);
        }
    }

    /**
     * Subscribe for newsletters
     * - If order for logged in user subscribe by userId
     * - If order for guest user subscribe by email
     *
     * @param OrderModel $order
     */
    public function subscribeForNewsletter($order) {
        $customerId = $order->getCustomerId();
        try {
            if ($customerId) {
                $this->subscriberFactory->create()->subscribeCustomerById($customerId);
            } else {
                $email = $order->getBillingAddress()->getEmail();
                $this->subscriberFactory->create()->subscribe($email);
            }
        } catch (\Exception $e) {
            // We are here if we are unable to send confirmation email, for example don't have transport
            $this->bugsnag->notifyException($e);
        }
    }
}