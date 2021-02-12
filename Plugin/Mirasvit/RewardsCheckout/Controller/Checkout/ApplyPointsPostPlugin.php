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

namespace Bolt\Boltpay\Plugin\Mirasvit\RewardsCheckout\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\ThirdPartyModules\Mirasvit\Rewards as BoltMirasvitRewards;

/**
 * Class ApplyPointsPostPlugin
 *
 */
class ApplyPointsPostPlugin
{
    /**
     * @var CustomerSession
     */
    private $customerSession;
    
    public function __construct(
        CustomerSession $customerSession
    ) {
        $this->customerSession = $customerSession;
    }
    
    /**
     * Check how the customer choose to spend the reward points
     *
     * @param \Mirasvit\RewardsCheckout\Controller\Checkout\ApplyPointsPost $subject
     * 
     * @return null
     */
    public function beforeExecute(\Mirasvit\RewardsCheckout\Controller\Checkout\ApplyPointsPost $subject)
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }
        
        if ($subject->getRequest()->isXmlHttpRequest()) {
            $points_all = $subject->getRequest()->getParam('points_all');

            if (!empty($points_all)) {
                $this->customerSession->setBoltMirasvitRewardsMode(BoltMirasvitRewards::MIRASVIT_REWARDS_APPLY_MODE_ALL);
            } else {
                $this->customerSession->setBoltMirasvitRewardsMode(BoltMirasvitRewards::MIRASVIT_REWARDS_APPLY_MODE_PART);
            }
        }
        
        return null;
    }
}
