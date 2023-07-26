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

namespace Bolt\Boltpay\Model\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Bolt\Boltpay\Api\Data\GetMaskedQuoteIDDataInterface;
use Bolt\Boltpay\Api\CartManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Exception;


class CartManagement implements CartManagementInterface
{
    /**
     * @var GetMaskedQuoteIDDataInterface
     */
    private $data;

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
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var QuoteIdMaskResource
     */
    private $quoteIdMaskResource;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param GetMaskedQuoteIDDataInterface $data
     * @param StoreManagerInterface $storeManager
     * @param Bugsnag $bugsnag
     * @param CartHelper $cartHelper
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        GetMaskedQuoteIDDataInterface  $data,
        StoreManagerInterface $storeManager,
        Bugsnag $bugsnag,
        CartHelper $cartHelper,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->data = $data;
        $this->storeManager = $storeManager;
        $this->bugsnag = $bugsnag;
        $this->cartHelper = $cartHelper;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /**
     * @api
     *
     * @param string $cartId
     *
     * @return \Bolt\Boltpay\Api\Data\GetMaskedQuoteIDDataInterface
     *
     * @throws WebapiException
     */
    public function getMaskedId($cartId = '')
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $maskedQuoteID = $quoteIdMask->load($cartId, 'quote_id')->getMaskedId();
            if (!$maskedQuoteID) {
                $maskedQuoteID = $this->generateMaskQuoteId($cartId);
                if (!$maskedQuoteID) {
                    throw new WebapiException(__('Masked quote ID does not found'), 0, WebapiException::HTTP_NOT_FOUND);
                }

            }
            $this->data->setMaskedQuoteId($maskedQuoteID);
            return $this->data;
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * @param $cartId
     * @return mixed
     */
    public function generateMaskQuoteId($cartId)
    {
        $this->quoteIdMaskFactory->create()->setQuoteId($cartId)->save();
        return $this->quoteIdMaskFactory->create()->load($cartId, 'quote_id')->getMaskedId();
    }

    /**
     * Set specific cart active
     *
     * @api
     *
     * @param mixed $cartId
     * @param mixed $isActive
     *
     * @return void
     *
     * @throws WebapiException
     */
    public function update($cartId = null, $isActive = null)
    {
        try {
            $quote = $this->cartHelper->getQuoteById($cartId);
            if (!$quote) {
                throw new WebapiException(__('Quote does not found'), 0, WebapiException::HTTP_NOT_FOUND);
            }

            $quote->setIsActive((bool)$isActive)->save();
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Get Cart Id from Masked Quote Id
     *
     * @param $maskedQuoteId
     * @return int
     *
     * @throws WebapiException
     */
    public function getCartIdByMaskedId($maskedQuoteId)
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId($maskedQuoteId);
            if (!$quoteId) {
                throw new WebapiException(__('Quote does not found'), 0, WebapiException::HTTP_NOT_FOUND);
            }

            return $quoteId;
        } catch (WebapiException $e) {
            $this->bugsnag->notifyException($e);
            throw $e;
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }

    private function maskedQuoteIdToQuoteId($maskedQuoteId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $maskedQuoteId, 'masked_id');

        $cart = $this->cartHelper->getQuoteById($quoteIdMask->getQuoteId());
        if (!$cart) {
            return null;
        }

        return (int)$cart->getId();
    }
}
