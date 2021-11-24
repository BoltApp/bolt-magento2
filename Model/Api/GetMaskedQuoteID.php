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

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Bolt\Boltpay\Api\Data\GetMaskedQuoteIDDataInterface;
use Bolt\Boltpay\Api\GetMaskedQuoteIDInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Exception;


class GetMaskedQuoteID implements GetMaskedQuoteIDInterface
{
    /**
     * @var GetMaskedQuoteIDDataInterface
     */
    private $data;


    /**
     * @var integer
     */
    private $storeID;

    /**
     * @var integer
     */
    private $websiteId;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param GetMaskedQuoteIDDataInterface $data
     * @param StoreManagerInterface $storeManager
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        GetMaskedQuoteIDDataInterface  $data,
        StoreManagerInterface $storeManager,
        Bugsnag $bugsnag
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->data = $data;
        $this->storeManager = $storeManager;
        $this->bugsnag = $bugsnag;
    }

    /**
     * @api
     *
     * @param string $quoteID
     * 
     * @return \Bolt\Boltpay\Api\Data\GetMaskedQuoteInterface
     *
     * @throws WebapiException
     */
    public function execute($quoteID = '')
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $maskedQuoteID = $quoteIdMask->load($quoteID, 'quote_id')->getMaskedId();
            if (!$maskedQuoteID) {
                throw new WebapiException(__('Masked quote ID does not found'), 0, WebapiException::HTTP_NOT_FOUND);
            }
            $this->data->setMaskedQuoteID($maskedQuoteID);
            return $this->data;
        } catch (WebapiException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}
