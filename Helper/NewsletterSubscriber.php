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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
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
class NewsletterSubscriber extends AbstractHelper
{
   
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
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Context $context,
        Bugsnag $bugsnag,
        SubscriberFactory $subscriberFactory
    ) {
        parent::__construct($context);
        $this->subscriberFactory = $subscriberFactory;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Subscribe for newsletters
     * - If order for logged in user subscribe by userId
     * - If order for guest user subscribe by email
     *
     * @param OrderModel $order
     */
    public function subscribe($order)
    {
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
