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

namespace Bolt\Boltpay\ThirdPartyModules\Magento;

use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\Escaper;
use Magento\Quote\Model\Quote\Item;
use Magento\GiftCard\Model\Catalog\Product\Type\Giftcard

class GiftCard
{

    /**
     * @var Bugsnag Bugsnag helper instance
     */
    private $bugsnagHelper;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param Escaper $escaper
     */
    public function __construct(
        Bugsnag  $bugsnagHelper,
        Escaper $escaper
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->escaper = $escaper;
    }

    /**
     * @param array      $result
     * @param Quote/Item $item
     * @return array
     */
    public function filterCartItemsProperties(
        $result,
        $item
    ) {
        try {
            $productType = $item instanceof Item ? $item->getProductType() : $item->getTypeId();
            if ($productType == Giftcard::TYPE_GIFTCARD) {
                $allowedOptions = [
                    'giftcard_sender_name',
                    'giftcard_recipient_name',
                    'giftcard_sender_email',
                    'giftcard_recipient_email',
                    'giftcard_message',
                ];
                foreach ($allowedOptions as $optionKey) {
                    $option = $item->getOptionByCode($optionKey);
                    if ($option) {
                        $value = $option->getValue();
                        if ($value) {
                            $result[] = (object) [
                                'name' => $optionKey,
                                'value' => trim($this->escaper->escapeHtml($value)),
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {
            return $result;
        }
    }
}
