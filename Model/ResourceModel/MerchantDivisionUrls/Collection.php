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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls;

use Bolt\Boltpay\Model\MerchantDivisionUrls;
use Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls as ResourceModelMerchantDivisionUrls;

/**
 * Class Collection
 *
 * @package Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'bolt_merchant_division_urls_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'bolt_merchant_division_urls_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(MerchantDivisionUrls::class, ResourceModelMerchantDivisionUrls::class);
    }
}