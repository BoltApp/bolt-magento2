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

namespace Bolt\Boltpay\ThirdPartyModules\Amasty;

use Amasty\Rewards\Helper\Data;
use Amasty\Rewards\Model\ResourceModel\Quote;
use Amasty\Rewards\Model\RewardsPropertyProvider;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Discount;
use Bolt\Boltpay\Helper\Shared\CurrencyUtils;
use Magento\Framework\App\ResourceConnection;

class Rewards
{
    const AMASTY_REWARD = 'amasty_rewards_point';

    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    protected $amastyRewardsResourceQuote;

    protected $amastyRewardsQuote;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Bugsnag $bugsnagHelper Bugsnag helper instance
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Bugsnag   $bugsnagHelper,
        ResourceConnection $resourceConnection
    ) {
        $this->bugsnagHelper   = $bugsnagHelper;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param $result
     * @param $amastyRewardsHelperData
     * @param $amastyRewardsResourceModelQuote
     * @param $quote
     * @param $parentQuote
     * @param $paymentOnly
     * @return array
     */
    public function collectDiscounts(
        $result,
        $amastyRewardsHelperData,
        $amastyRewardsResourceModelQuote,
        $quote,
        $parentQuote,
        $paymentOnly
    ) {
        list ($discounts, $totalAmount, $diff) = $result;

        try {
            $amount = 0;
            $pointsUsed = null;
            if ($quote->getData('amrewards_point')) {
                $rewardData = $amastyRewardsHelperData->getRewardsData();
                $pointsUsed = $amastyRewardsResourceModelQuote->getUsedRewards($quote->getId());
                $pointsRate = $rewardData['rateForCurrency'];
                $amount = $pointsUsed / $pointsRate;
            } elseif ($pointsUsed = $quote->getData('am_spent_reward_points')) {
                $rewardData = $amastyRewardsHelperData->getRewardsData();
                $pointsRate = $rewardData['rateForCurrency'];
                $amount = floatval($pointsUsed) / $pointsRate;
            }
            if ($pointsUsed && $amount > 0) {
                $currencyCode = $quote->getQuoteCurrencyCode();
                $roundedAmount = CurrencyUtils::toMinor($amount, $currencyCode);
                $discounts[] = [
                    'description'       => 'Reward Points',
                    'amount'            => $roundedAmount,
                    'reference'         => self::AMASTY_REWARD,
                    'discount_category' => Discount::BOLT_DISCOUNT_CATEGORY_STORE_CREDIT,
                    // For v1/discounts.code.apply and v2/cart.update
                    'discount_type'     => Discount::BOLT_DISCOUNT_TYPE_FIXED,
                    // For v1/merchant/order
                    'type'              => Discount::BOLT_DISCOUNT_TYPE_FIXED,
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
    public function filterVerifyAppliedStoreCredit(
        $result,
        $couponCode,
        $quote
    ) {
        if ($couponCode == self::AMASTY_REWARD && ($quote->getData('amrewards_point') || $quote->getData('am_spent_reward_points'))) {
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
    public function removeAppliedStoreCredit(
        $amastyRewardsManagement,
        $amastyRewardsQuote,
        $couponCode,
        $quote,
        $websiteId,
        $storeId
    ) {
        try {
            if ($couponCode == self::AMASTY_REWARD && ($quote->getData('amrewards_point') || $quote->getData('am_spent_reward_points'))) {
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
     * @param $amastyRewardsResourceQuote
     * @param $amastyRewardsQuote
     * @param $immutableQuote
     * @return void
     */
    public function applyExternalDiscountData(
        $amastyRewardsResourceQuote,
        $amastyRewardsQuote,
        $immutableQuote
    ) {
        $this->amastyRewardsResourceQuote = $amastyRewardsResourceQuote;
        $this->amastyRewardsQuote = $amastyRewardsQuote;

        $this->setAmastyRewardPoints($immutableQuote);
    }

    /**
     * If Amasty Reward Points extension is present clone applied reward points
     *
     * @param $amastyRewardsResourceQuote
     * @param $amastyRewardsQuote
     * @param $sourceQuote
     * @param $destinationQuote
     * @return void
     */
    public function replicateQuoteData(
        $amastyRewardsResourceQuote,
        $amastyRewardsQuote,
        $sourceQuote,
        $destinationQuote
    ) {
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
     * @param $source
     * @param $destination
     * @return void
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
     * @param $quote
     * @return void
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
     * @param $quote
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
