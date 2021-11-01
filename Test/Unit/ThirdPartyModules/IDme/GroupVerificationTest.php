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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\IDme;

use Bolt\Boltpay\Test\Unit\TestHelper;
use Bolt\Boltpay\ThirdPartyModules\IDme\GroupVerification;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Customer\Model\Session;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class GroupVerificationTest
 *
 * @package Bolt\Boltpay\Test\Unit\ThirdPartyModules\IDme
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\IDme\GroupVerification
 */
class GroupVerificationTest extends BoltTestCase
{
    const IDME_UUID = 'IDME_UUID';
    const IDME_GROUP = 'IDME_GROUP';
    const IDME_SUBGROUP = 'IDME_SUBGROUP';

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var GroupVerification
     */
    private $groupVerification;
    private $objectManager;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->groupVerification = $this->objectManager->create(GroupVerification::class);
        $this->quote = $this->objectManager->create(Quote::class);
        $this->customerSession = $this->objectManager->create(Session::class);

    }

    /**
     * @test
     * @covers ::beforeApplyDiscount
     */
    public function beforeApplyDiscount()
    {
        $this->quote->setIdmeUuid(self::IDME_UUID);
        $this->quote->setIdmeGroup(self::IDME_GROUP);
        $this->quote->setIdmeSubgroups(self::IDME_SUBGROUP);
        TestHelper::setProperty($this->groupVerification,'customerSession', $this->customerSession);
        $this->groupVerification->beforeApplyDiscount($this->quote);
        self::assertEquals(self::IDME_UUID, $this->customerSession->getData('idme_uuid'));
        self::assertEquals(self::IDME_GROUP, $this->customerSession->getData('idme_group'));
        self::assertEquals(self::IDME_SUBGROUP, $this->customerSession->getData('idme_subgroups'));
        $this->unsetIdmeCustomer();
    }

    /**
     * @test
     * that collectSessionData will collect IDMe related data from session and append it to the provided $sessionData
     *
     * @covers ::collectSessionData
     */
    public function collectSessionData_withIDMeDataInSession_appendsTheDataToCollectedSessionData()
    {
        $idmeUuid = sha1('idme_uuid');
        $idmeGroup = sha1('idme_group');
        $idmeSubgroups = sha1('idme_subgroups');

        $this->customerSession->setIdmeUuid($idmeUuid);
        $this->customerSession->setIdmeGroup($idmeGroup);
        $this->customerSession->setIdmeSubgroups($idmeSubgroups);
        TestHelper::setProperty($this->groupVerification,'customerSession', $this->customerSession);
        $result = $this->groupVerification->collectSessionData([], $this->quote, $this->quote);
        static::assertEquals(
            [
                'idme_uuid'      => $idmeUuid,
                'idme_group'     => $idmeGroup,
                'idme_subgroups' => $idmeSubgroups,
            ],
            $result
        );
        $this->unsetIdmeCustomer();
    }

    /**
     * @test
     * that restoreSessionData will restore IDMe related data from provided array onto the customer session
     *
     * @covers ::restoreSessionData
     */
    public function restoreSessionData_withIDMeDataInSession_appendsTheDataToRestoredSessionData()
    {
        $idmeUuid = sha1('idme_uuid');
        $idmeGroup = sha1('idme_group');
        $idmeSubgroups = sha1('idme_subgroups');

        TestHelper::setProperty($this->groupVerification,'customerSession', $this->customerSession);
        $this->groupVerification->restoreSessionData(
            [
                'idme_uuid'      => $idmeUuid,
                'idme_group'     => $idmeGroup,
                'idme_subgroups' => $idmeSubgroups,
            ]
        );
        self::assertEquals($idmeUuid, $this->customerSession->getData('idme_uuid'));
        self::assertEquals($idmeGroup, $this->customerSession->getData('idme_group'));
        self::assertEquals($idmeSubgroups, $this->customerSession->getData('idme_subgroups'));
        $this->unsetIdmeCustomer();
    }

    public function unsetIdmeCustomer(){
        $this->customerSession->unsIdmeUuid();
        $this->customerSession->unsIdmeGroup();
        $this->customerSession->unsIdmeSubgroups();
    }
}
