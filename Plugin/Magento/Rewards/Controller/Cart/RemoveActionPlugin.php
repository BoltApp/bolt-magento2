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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Magento\Rewards\Controller\Cart;

/**
 * Plugin for {@see \Magento\Reward\Controller\Cart\Remove} used to add AJAX support
 */
class RemoveActionPlugin
{
    /**
     * @var \Bolt\Boltpay\Helper\Config Bolt configuration helper instance
     */
    private $configHelper;

    /**
     * @param \Bolt\Boltpay\Helper\Config $configHelper Bolt configuration helper instance
     */
    public function __construct(\Bolt\Boltpay\Helper\Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * The original behavior intended for the shopping cart page is to trigger
     * the browser to reload by sending a "Location" header. This is ok
     * from the shopping cart page, but we do not want this from the mini-cart.
     * To improve UX we remove the "Location" header so that the page does not
     * "randomly" reload the user's page. This is consistent with other native
     * mini-cart edit behavior.
     *
     * @see \Magento\Reward\Controller\Cart\Remove::execute
     *
     * @param \Magento\Reward\Controller\Cart\Remove        $subject the frontend controller wrapped by this plugin
     * @param void|\Magento\Framework\App\ResponseInterface $result original result of the wrapped method
     *
     * @return void|\Magento\Framework\App\ResponseInterface original result of the wrapped method
     */
    public function afterExecute(\Magento\Reward\Controller\Cart\Remove $subject, $result)
    {
        if ($this->configHelper->displayRewardPointsInMinicartConfig() && $subject->getRequest()->isAjax()) {
            $subject->getResponse()->clearHeader('Location');
            $subject->getResponse()->setStatusHeader(200);
        }
        return $result;
    }
}
