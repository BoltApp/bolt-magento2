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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Zonos\DutyTax\Model\Carrier;

use Bolt\Boltpay\Helper\Bugsnag as BoltBugsnagHelper;
use Bolt\Boltpay\Helper\Cart as BoltCartHelper;
use Zonos\DutyTax\Model\Carrier\Zonos as ZonosCarrier;

/**
 * Class ZonosPlugin
 *
 * This overrides {@see \Zonos\DutyTax\Model\Carrier\Zonos}
 */
class ZonosPlugin
{

    /**
     * @var BoltBugsnagHelper
     */
    private $boltBugsnagHelper;
    
    /**
     * @var BoltCartHelper
     */
    private $boltCartHelper;

    /**
     * ZonosPlugin constructor.
     *
     * @param BoltBugsnagHelper $boltBugsnagHelper
     * @param BoltCartHelper $boltCartHelper
     */
    public function __construct(
        BoltBugsnagHelper $boltBugsnagHelper,
        BoltCartHelper $boltCartHelper
    ) {
        $this->boltBugsnagHelper = $boltBugsnagHelper;
        $this->boltCartHelper = $boltCartHelper;
    }

    /**
     * For each method of Zonos shipping, the Zonos plugin would add an unique identifier at the end of the service
     * id in order to retrieve the correct quote, the generation of this unique identifier is based on the address data
     * and the related quote id.
     *
     * Due to the logic in the Bolt checkout, the quote in shipping step is different from the one in order creation,
     * so the identifier key of selected Zonos shipping method changes, and Magento throws out such an exception
     * `The shipping method is missing.`.
     *
     * To fix this issue, this plugin function returns the Bolt parent quote id instead of quote id,
     * cause in the Bolt checkout, the order creation uses parent quote.
     *
     */
    public function beforeGetUniqueIdentifierKey(ZonosCarrier $subject, $address, $quoteId)
    {
        try {
            $quote = $this->boltCartHelper->getQuoteById($quoteId);
            if ($quote) {
                $parentQuoteId = $quote->getBoltParentQuoteId();
                if (!empty($parentQuoteId)) {
                    $quoteId = $parentQuoteId;
                }
            }
        } catch (\Exception $e) {
            $this->boltBugsnagHelper->notifyException($e);
        }

        return [$address, $quoteId];
    }
}
