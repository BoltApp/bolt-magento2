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

namespace Bolt\Boltpay\ThirdPartyModules\IDme;

use Magento\Customer\Model\Session;

class GroupVerification
{
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
