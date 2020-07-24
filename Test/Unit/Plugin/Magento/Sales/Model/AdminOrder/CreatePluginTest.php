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

namespace Bolt\Boltpay\Test\Unit\Plugin\Magento\Model\AdminOrder;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Bolt\Boltpay\Plugin\Magento\Sales\Model\AdminOrder\CreatePlugin;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Backend\Model\Session\Quote as AdminCheckoutSession;
use Magento\Sales\Model\AdminOrder\Create;

/**
 * Class CreatePlugin
 * @package Bolt\Boltpay\Test\Unit\Plugin\Magento\Model\AdminOrder;
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Magento\Sales\Model\AdminOrder\CreatePlugin
 */
class CreatePluginTest extends TestCase
{
    const STORE_PICKUP_ADDRESS_DATA = [
        'city' => 'Knoxville',
        'country_id' => 'US',
        'postcode' => '37921',
        'region_code' => 'TN',
        'region_id' => '56',
        'street' => '4535 ANNALEE Way
Room 4000',
    ];

    const NORMAL_ADDRESS = [
        'city' => 'Knoxville',
        'country_id' => 'US',
        'postcode' => '37921',
        'region_code' => 'TN',
        'region_id' => '78',
        'street' => '4611 ANNALEE Way
Room 1111',
    ];

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var AdminCheckoutSession
     */
    protected $adminCheckoutSession;

    /**
     * @var Create
     */
    protected $createModel;

    /** @var CreatePlugin */
    protected $plugin;

    protected function setUp()
    {
        $this->configHelper = $this->createPartialMock(ConfigHelper::class, ['isStorePickupFeatureEnabled', 'isPickupInStoreShippingMethodCode', 'getPickupAddressData']);
        $this->adminCheckoutSession = $this->createPartialMock(AdminCheckoutSession::class, ['setData', 'getData', 'unsetData']);
        $this->createModel = $this->createPartialMock(Create::class, [
            'getShippingAddress',
            'getData',
            'addData'
        ]);
        // initialize test object
        $objectManager = new ObjectManager($this);
        $this->plugin = $objectManager->getObject(
            CreatePlugin::class,
            [
                'configHelper' => $this->configHelper,
                'adminCheckoutSession' => $this->adminCheckoutSession
            ]
        );
    }

    /**
     * @test
     * @covers ::beforeImportPostData
     */
    public function beforeImportPostData_ifStorePickupFeatureIsDisabled()
    {
        $this->configHelper->expects(self::once())->method('isStorePickupFeatureEnabled')->willReturn(false);
        $this->configHelper->expects(self::never())->method('isPickupInStoreShippingMethodCode')->willReturnSelf();
        $this->adminCheckoutSession->expects(self::never())->method('getData')->with('old_shipping_address')->willReturnSelf();
        $this->plugin->beforeImportPostData($this->createModel, ['shipping_method' => 'instorepickup_instorepickup_']);
    }

    /**
     * @test
     * @covers ::beforeImportPostData
     */
    public function beforeImportPostData_ifCustomerChooseStorePickUpMethod()
    {
        $this->configHelper->expects(self::once())->method('isStorePickupFeatureEnabled')->willReturn(true);
        $this->configHelper->expects(self::once())->method('isPickupInStoreShippingMethodCode')->with('instorepickup_instorepickup_')->willReturn(true);

        $this->createModel->expects(self::exactly(2))->method('getShippingAddress')->willReturnSelf();
        $this->createModel->expects(self::once())->method('getData')->willReturn(self::NORMAL_ADDRESS);
        $this->adminCheckoutSession->expects(self::once())->method('setData')->with('old_shipping_address', self::NORMAL_ADDRESS)->willReturnSelf();

        $this->configHelper->expects(self::once())->method('getPickupAddressData')->willReturn(self::STORE_PICKUP_ADDRESS_DATA);
        $this->createModel->expects(self::once())->method('addData')->with(self::STORE_PICKUP_ADDRESS_DATA)->willReturnSelf();

        $this->plugin->beforeImportPostData($this->createModel, ['shipping_method' => 'instorepickup_instorepickup_']);
    }

    /**
     * @test
     * @covers ::beforeImportPostData
     */
    public function beforeImportPostData_ifCustomerChooseShippingMethodIsStorePickup()
    {
        $this->configHelper->expects(self::once())->method('isStorePickupFeatureEnabled')->willReturn(true);
        $this->configHelper->expects(self::once())->method('isPickupInStoreShippingMethodCode')->with('is_not_instorepickup_instorepickup_')->willReturn(false);

        $this->adminCheckoutSession->expects(self::once())->method('getData')->with('old_shipping_address')->willReturn(false);
        $this->createModel->expects(self::never())->method('getShippingAddress')->willReturnSelf();

        $this->plugin->beforeImportPostData($this->createModel, ['shipping_method' => 'is_not_instorepickup_instorepickup_']);
    }

    /**
     * @test
     * @covers ::beforeImportPostData
     */
    public function beforeImportPostData_ifCustomerChooseStorePickupMethodThenChooseAnotherMethod()
    {
        $this->configHelper->expects(self::once())->method('isStorePickupFeatureEnabled')->willReturn(true);
        $this->configHelper->expects(self::once())->method('isPickupInStoreShippingMethodCode')->with('is_not_instorepickup_instorepickup_')->willReturn(false);

        $this->adminCheckoutSession->expects(self::once())->method('getData')->with('old_shipping_address')->willReturn(self::NORMAL_ADDRESS);
        $this->adminCheckoutSession->expects(self::once())->method('unsetData')->with('old_shipping_address')->willReturnSelf();

        $this->createModel->expects(self::once())->method('getShippingAddress')->willReturnSelf();
        $this->createModel->expects(self::once())->method('addData')->with(self::NORMAL_ADDRESS)->willReturnSelf();

        $this->plugin->beforeImportPostData($this->createModel, ['shipping_method' => 'is_not_instorepickup_instorepickup_']);
    }
}
