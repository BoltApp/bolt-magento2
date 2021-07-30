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
 *
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Observer;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RemoveBlocksObserver implements ObserverInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var LogHelper
     */
    protected $logHelper;

    /**
     * @param ConfigHelper $configHelper
     * @param LogHelper    $logHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        LogHelper $logHelper
    ) {
        $this->configHelper = $configHelper;
        $this->logHelper = $logHelper;
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        $BoltSSOPages = [ConfigHelper::LOGIN_PAGE_ACTION, ConfigHelper::CREATE_ACCOUNT_PAGE_ACTION];
        if (in_array($observer->getData('full_action_name'), $BoltSSOPages)) {
            $layout = $observer->getLayout();
            if ($this->configHelper->isBoltSSOEnabled()) {
                // Remove native block on login page
                $layout->unsetElement('customer_form_login');
                $layout->unsetElement('customer.new');
                // Remove native block on register page
                $layout->unsetElement('customer_form_register');
            } else {
                // Remove Bolt SSO elements
                $layout->unsetElement('bolt_sso_login');
                $layout->unsetElement('bolt_sso_register');
            }    
        }
    }
}
