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
use Bolt\Boltpay\Helper\Order as OrderHelper;

/**
 * Boltpay web hook helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckboxesHandler extends AbstractHelper
{
    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @param Context $context
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Context $context,
        OrderHelper $orderHelper
    )
    {
        parent::__construct($context);
        $this->orderHelper = $orderHelper;
    }

    public function handle($displayId, $checkboxes)
    {
        $comment = '';
        $needSubscribe = false;
        foreach ($checkboxes as $checkbox) {
            if ($checkbox['category'] == 'NEWSLETTER' && $checkbox['value']) {
                $needSubscribe = true;
            }
            $comment .= '<br>' . $checkbox['text'] . ': ' . ($checkbox['value'] ? 'Yes' : 'No');
        }
        if ($comment) {
            $commentPrefix = 'BOLTPAY INFO :: checkboxes';
            $this->orderHelper->addCommentToStatusHistoryIfNotExists($displayId, $commentPrefix.$comment, $commentPrefix);
        }
        if ($needSubscribe) {
            $this->orderHelper->subscribeForNewsletter($displayId);
        }
    }
}