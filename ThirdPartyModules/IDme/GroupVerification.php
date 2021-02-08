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

namespace Bolt\Boltpay\ThirdPartyModules\IDme;

use Bolt\Boltpay\Model\ThirdPartyEvents;
use Magento\Customer\Model\Session;

class GroupVerification
{
    use ThirdPartyEvents\CollectsSessionData;
    use ThirdPartyEvents\RestoresSessionData;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * GroupVerification constructor.
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    )
    {
        $this->customerSession = $customerSession;
    }

    /**
     * @param $quote
     */
    public function beforeApplyDiscount($quote)
    {
        $this->applyIDMeDataToCustomerSession($quote);
    }

    /**
     * @param $quote
     */
    public function afterLoadSession($quote)
    {
        $this->applyIDMeDataToCustomerSession($quote);
    }

    /**
     * @param array                      $sessionData
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote $immutableQuote
     *
     * @return array
     */
    public function collectSessionData($sessionData, $quote, $immutableQuote)
    {
        if ($this->customerSession->getData('idme_uuid')) {
            $sessionData['idme_uuid'] = $this->customerSession->getData('idme_uuid');
        }
        if ($this->customerSession->getData('idme_group')) {
            $sessionData['idme_group'] = $this->customerSession->getData('idme_group');
        }
        if ($this->customerSession->getData('idme_subgroups')) {
            $sessionData['idme_subgroups'] = $this->customerSession->getData('idme_subgroups');
        }
        return $sessionData;
    }

    /**
     * @param array                      $sessionData
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return void
     */
    public function restoreSessionData($sessionData, $quote = null)
    {
        if (key_exists('idme_uuid', $sessionData)) {
            $this->customerSession->setData('idme_uuid', $sessionData['idme_uuid']);
        }
        if (key_exists('idme_group', $sessionData)) {
            $this->customerSession->setData('idme_group', $sessionData['idme_group']);
        }
        if (key_exists('idme_subgroups', $sessionData)) {
            $this->customerSession->setData('idme_subgroups', $sessionData['idme_subgroups']);
        }
    }

    /**
     * Apply IdMe data to customer Session to correct the discount
     * @param $quote
     */
    private function applyIDMeDataToCustomerSession($quote)
    {
        if ($idMeUuid = $quote->getIdmeUuid()) {
            $this->customerSession->setIdmeUuid($idMeUuid);
        }

        if ($idMeGroup = $quote->getIdmeGroup()) {
            $this->customerSession->setIdmeGroup($idMeGroup);
        }

        if ($idMeSubgroups = $quote->getIdmeSubgroups()) {
            $this->customerSession->setIdmeSubgroups($idMeSubgroups);
        }
    }
}
