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

use Bolt\Boltpay\ThirdPartyModules\IDme\GroupVerification;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Customer\Model\Session;
use PHPUnit\Framework\MockObject\MockObject;

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
     * @var Session|MockObject
     */
    private $customerSession;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @var GroupVerification|MockObject
     */
    private $currentMock;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->customerSession = $this->quote = $this->createPartialMock(
            Session::class,
            [
                'setIdmeUuid',
                'setIdmeGroup',
                'setIdmeSubgroups',
                'getData',
                'setData'
            ]
        );
        $this->quote = $this->createPartialMock(
            Quote::class,
            [
                'getIdmeUuid',
                'getIdmeGroup',
                'getIdmeSubgroups',
            ]
        );
        $this->currentMock = (new ObjectManager($this))->getObject(
            GroupVerification::class,
            [
                'customerSession' => $this->customerSession
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeApplyDiscount
     */
    public function beforeApplyDiscount()
    {
        $this->quote->expects(self::once())->method('getIdmeUuid')->willReturn(self::IDME_UUID);
        $this->quote->expects(self::once())->method('getIdmeGroup')->willReturn(self::IDME_GROUP);
        $this->quote->expects(self::once())->method('getIdmeSubgroups')->willReturn(self::IDME_SUBGROUP);
        $this->customerSession->expects(self::once())->method('setIdmeUuid')->with(self::IDME_UUID)->willReturnSelf();
        $this->customerSession->expects(self::once())->method('setIdmeGroup')->with(self::IDME_GROUP)->willReturnSelf();
        $this->customerSession->expects(self::once())->method('setIdmeSubgroups')
            ->with(self::IDME_SUBGROUP)->willReturnSelf();
        $this->currentMock->beforeApplyDiscount($this->quote);
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
        $this->customerSession->expects(static::exactly(6))->method('getData')->willReturnMap(
            [
                ['idme_uuid', false, $idmeUuid],
                ['idme_group', false, $idmeGroup],
                ['idme_subgroups', false, $idmeSubgroups],
            ]
        );
        $result = $this->currentMock->collectSessionData([], $this->quote, $this->quote);
        static::assertEquals(
            [
                'idme_uuid'      => $idmeUuid,
                'idme_group'     => $idmeGroup,
                'idme_subgroups' => $idmeSubgroups,
            ],
            $result
        );
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
        $this->customerSession->expects(static::exactly(3))->method('setData')->withConsecutive(
            ['idme_uuid', $idmeUuid],
            ['idme_group', $idmeGroup],
            ['idme_subgroups', $idmeSubgroups]
        );
        $this->currentMock->restoreSessionData(
            [
                'idme_uuid'      => $idmeUuid,
                'idme_group'     => $idmeGroup,
                'idme_subgroups' => $idmeSubgroups,
            ]
        );
    }
}
