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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;

class Rewards
{
    const AMASTY_REWARD = 'amasty_rewards_point';
    
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;
    
    /**
     * @var Discount
     */
    protected $discountHelper;
    
    /**
     * @var \Amasty\Rewards\Model\ResourceModel\Quote
     */
    protected $amastyRewardsResourceQuote;
    
    /**
     * @var \Amasty\Rewards\Model\Quote
     */
    protected $amastyRewardsQuote;
    
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param Discount $discountHelper
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag   $bugsnagHelper,
        Discount  $discountHelper,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->bugsnagHelper   = $bugsnagHelper;
        $this->discountHelper  = $discountHelper;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param array $result
     * @param Amasty\Rewards\Helper\Data $amastyRewardsHelperData
     * @param Quote $quote
     * 
     * @return array
     */
    public function collectDiscounts($result,
                                     $amastyRewardsHelperData,
                                     $amastyRewardsResourceModelQuote,
                                     $quote,
                                     $parentQuote,
                                     $paymentOnly)
    {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            if ($quote->getData('amrewards_point')) {
                $rewardData = $amastyRewardsHelperData->getRewardsData();
                $pointsUsed = $amastyRewardsResourceModelQuote->getUsedRewards($quote->getId());
                $pointsRate = $rewardData['rateForCurrency'];
                $amount = $pointsUsed / $pointsRate;
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'reference'         => self::AMASTY_REWARD,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    'discount_type'     => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/discounts.code.apply and v2/cart.update
                    'type'              => $this->discountHelper->getBoltDiscountType('by_fixed'), // For v1/merchant/order
                ];
                
                $diff -= CurrencyUtils::toMinorWithoutRounding($amount, $currencyCode) - $roundedAmount;
                $totalAmount -= $roundedAmount;
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        } finally {        
            return [$discounts, $totalAmount, $diff];
        }
    }

    public function getAdditionalJS($result)
    {
        $result .= 'var selectorsForInvalidate = ["apply-amreward","cancel-amreward"];
        for (var i = 0; i < selectorsForInvalidate.length; i++) {
            var button = document.getElementById(selectorsForInvalidate[i]);
            if (button) {
                button.addEventListener("click", function() {
                    if (localStorage) {
                        localStorage.setItem("bolt_cart_is_invalid", "true");
                    }
                }, false);
            }
        }';
        return $result;
    }
    
    /**
     * Return code if the quote has Amasty reward points.
     * 
     * @param $result
     * @param $couponCode
     * @param $quote
     * 
     * @return array
     */
    public function filterVerifyAppliedStoreCredit (
        $result,
        $couponCode,
        $quote
    )
    {
        if ($couponCode == self::AMASTY_REWARD && $quote->getData('amrewards_point')) {
            $result[] = $couponCode;
        }
        
        return $result;
    }
    
    /**
     * Remove Amasty reward points from the quote.
     *
     * @param $amastyRewardsManagement
     * @param $amastyRewardsQuote
     * @param $couponCode
     * @param $quote
     * @param $websiteId
     * @param $storeId
     * 
     */
    public function removeAppliedStoreCredit (
        $amastyRewardsManagement,
        $amastyRewardsQuote,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    )
    {
        try {
            if ($couponCode == self::AMASTY_REWARD && $quote->getData('amrewards_point')) {
                $amastyRewardsManagement->collectCurrentTotals($quote, 0);

                $amastyRewardsQuote->addReward(
                    $quote->getId(),
                    0
                );
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Amasty reward points are held in a separate table
     * and are not assigned to the quote / totals directly out of the customer session.
     * 
     * @param \Amasty\Rewards\Model\ResourceModel\Quote $amastyRewardsResourceQuote
     * @param \Amasty\Rewards\Model\Quote  $amastyRewardsQuote
     * @param $immutableQuote
     */
    public function applyExternalDiscountData(
        $amastyRewardsResourceQuote,
        $amastyRewardsQuote,
        $immutableQuote
    )
    {
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;

        $this->setAmastyRewardPoints($immutableQuote);
    }
    
    /**
     * If Amasty Reward Points extension is present clone applied reward points
     * 
     * @param \Amasty\Rewards\Model\ResourceModel\Quote $amastyRewardsResourceQuote
     * @param \Amasty\Rewards\Model\Quote  $amastyRewardsQuote
     * @param $sourceQuote
     * @param $destinationQuote
     */
    public function replicateQuoteData(
        $amastyRewardsResourceQuote,
        $amastyRewardsQuote,
        $sourceQuote,
        $destinationQuote
    )
    {
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;

        $this->setAmastyRewardPoints($sourceQuote, $destinationQuote);
    }
    
    /**
     * Copy Amasty Reward Points data from source to destination quote.
     * The reward points are fetched from the 3rd party module DB table (amasty_rewards_quote)
     * and assigned to the destination quote temporarily (not persistent to the quote table).
     * This is the reason there are cases when the reward points data is read for and applied
     * to the (source) quote itself. The data is needed to be set before the quote totals are calculated,
     * for example in the Shipping and Tax call.
     *
     * @param Quote $source
     * @param Quote|null $destination
     */
    public function setAmastyRewardPoints($source, $destination = null)
    {
        if ($destination === null) {
            $destination = $source;
        }
        
        $amastyQuote = $this->amastyRewardsResourceQuote->loadByQuoteId($source->getId());

        if ($amastyQuote) {
            $amastyRewardPoints = $this->amastyRewardsResourceQuote->getUsedRewards($source->getId());
            $this->amastyRewardsQuote->addReward($destination->getId(), $amastyRewardPoints);
            $destination->setAmrewardsPoint($amastyRewardPoints);
        }
    }
    
    /**
     * Try to clear Amasty Reward Points data for the immutable quotes
     *
     * @param Quote $quote parent quote
     */
    public function deleteRedundantDiscounts($quote)
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $rewardsTable = $this->resourceConnection->getTableName('amasty_rewards_quote');
            $quoteTable = $this->resourceConnection->getTableName('quote');

            $sql = "DELETE FROM {$rewardsTable} WHERE quote_id IN
                    (SELECT entity_id FROM {$quoteTable}
                    WHERE bolt_parent_quote_id = :bolt_parent_quote_id AND entity_id != :entity_id)";
            $bind = [
                'bolt_parent_quote_id' => $quote->getBoltParentQuoteId(),
                'entity_id' => $quote->getBoltParentQuoteId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
    
    /**
     * Remove Amasty Reward Points quote info
     *
     * @param Quote $quote
     */
    public function clearExternalData($quote)
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $rewardsTable = $this->resourceConnection->getTableName('amasty_rewards_quote');

            $sql = "DELETE FROM {$rewardsTable} WHERE quote_id = :quote_id";
            $bind = [
                'quote_id' => $quote->getId()
            ];

            $connection->query($sql, $bind);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
