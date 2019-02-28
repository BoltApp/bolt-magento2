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
namespace Bolt\Boltpay\Model;

use Magento\Framework\Model\AbstractModel;
use Bolt\Boltpay\Api\Data\MerchantDivisionUrlsInterface;
use Bolt\Boltpay\Model\ResourceModel\MerchantDivisionUrls as ResourceModelMerchantDivisionUrls;

class MerchantDivisionUrls extends AbstractModel implements MerchantDivisionUrlsInterface
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModelMerchantDivisionUrls::class);
    }

    /**
     * @inheritdoc
     */
    public function getDivisionId()
    {
        return $this->getData(self::DIVISION_ID);
    }

    /**
     * @inheritdoc
     */
    public function setDivisionId($id)
    {
        $this->setData(self::DIVISION_ID, $id);
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritdoc
     */
    public function getUrl()
    {
        return $this->getData(self::URL);
    }

    /**
     * @inheritdoc
     */
    public function setUrl($url)
    {
        $this->setData(self::URL, $url);
    }
}
