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
namespace Bolt\Boltpay\Model\Config\Source\Catalog\Ingestion;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options provider for instant inventory sync events
 */
class Events implements OptionSourceInterface
{
    public const STOCK_STATUS_CHANGES = 'product_stock_status';

    /**
     * Get available instant inventory sync events
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::STOCK_STATUS_CHANGES, 'label' => __('Product Stock Status Changes')] // @phpstan-ignore-line
        ];
    }
}
