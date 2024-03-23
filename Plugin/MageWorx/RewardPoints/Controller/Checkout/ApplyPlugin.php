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

namespace Bolt\Boltpay\Plugin\MageWorx\RewardPoints\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\RewardPoints as BoltMageWorxRewards;

class ApplyPlugin
{
    /**
     * @var CustomerSession|mixed
     */
    private $customerSession;


    /**
     * MageWorxRewardPointsControllerCheckoutApplyPlugin constructor.
     *
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        CustomerSession $customerSession
    ) {
        $this->customerSession = $customerSession;
    }

    /**
     * Check how the customer choose to spend the reward points.
     *
     * @return mixed
     */
    public function beforeExecute(\MageWorx\RewardPoints\Controller\Checkout\Apply $subject) {
        /** @var mixed $subject */
        if ($subject->getRequest()->isXmlHttpRequest()) {
            $amount = $subject->getRequest()->getParam('amount');
            if (is_null($amount)) {
                $this->customerSession->setBoltMageWorxRewardsMode(
                    BoltMageWorxRewards::MAGEWORX_REWARDS_APPLY_MODE_ALL
                );
            } else {
                $this->customerSession->setBoltMageWorxRewardsMode(
                    BoltMageWorxRewards::MAGEWORX_REWARDS_APPLY_MODE_PART
                );
            }
        }

        return null;
    }
}
