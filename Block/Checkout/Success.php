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
namespace Bolt\Boltpay\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class Success
 *
 * @package Bolt\Boltpay\Block\Checkout
 */
class Success extends Template
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * Success constructor.
     *
     * @param ProductMetadataInterface $productMetadata
     * @param Context                  $context
     * @param array                    $data
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->productMetadata = $productMetadata;
    }

    /**
     * @return bool
     */
    public function isAllowInvalidateQuote()
    {
        // Workaround for known magento issue - https://github.com/magento/magento2/issues/12504
        return (bool) (version_compare($this->getMagentoVersion(), '2.2.0', '<'));
    }

    /**
     * Get magento version
     *
     * @return string
     */
    private function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }
}
