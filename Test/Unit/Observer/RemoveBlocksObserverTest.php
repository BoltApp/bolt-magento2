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

namespace Bolt\Boltpay\Test\Unit\Observer;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Observer\RemoveBlocksObserver;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout;

/**
 * Class RemoveBlocksObserver
 *
 * @coversDefaultClass \Bolt\Boltpay\Observer\RemoveBlocksObserver
 */
class RemoveBlocksObserverTest extends BoltTestCase
{
    /**
     * @var RemoveBlocksObserver
     */
    protected $currentMock;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Decider|\PHPUnit\Framework\MockObject\MockObject
     */
    private $deciderMock;

    /**
     * @test
     */
    public function execute_unsetsElements_ifBoltSSOEnabled()
    {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('customer_account_login');
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $layout->expects(static::exactly(3))->method('unsetElement')->withConsecutive(
            ['customer_form_login'],
            ['customer.new'],
            ['customer_form_register']
        );
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    public function execute_doesNotUnsetElements_ifNotLoginOrRegisterPage()
    {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout', 'getData']);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('checkout_cart_index');
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getLayout');
        $layout->expects(static::never())->method('unsetElement');
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    public function execute_doesNotUnsetElements_ifBoltSSONotDisabled()
    {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('customer_account_login');
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(false);
        $layout->expects(static::exactly(2))->method('unsetElement')->withConsecutive(
            ['bolt_sso_login'],
            ['bolt_sso_register']
        );
        $this->currentMock->execute($eventObserver);
    }

    protected function setUpInternal()
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->deciderMock = $this->createMock(Decider::class);
        $this->currentMock = new RemoveBlocksObserver($this->configHelper, $this->logHelper, $this->deciderMock);
    }

    /**
     * @test
     * that the observer will remove(unset) relevant account navigation links if the following preconditions are met:
     * 1. The action name starts with 'customer_account' since the links are added in 
     * 
     * @return void
     * @dataProvider execute_ifPreconditionsAreMet_unsetsNavigationLinksProvider
     * @covers ::execute
     */
    public function execute_ifPreconditionsAreMet_unsetsNavigationLinks(
        $fullActionName,
        $isPreventSsoCustomersFromEditingAccountInformation,
        $isBoltSsoFsEnabled,
        $isBoltSsoConfigEnabled,
        $shouldUnsetLinks
    ) {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')
            ->willReturn($fullActionName);
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->method('isBoltSSOEnabled')->willReturn($isBoltSsoConfigEnabled);
        $this->deciderMock->method('isBoltSSOEnabled')->willReturn($isBoltSsoFsEnabled);
        $this->deciderMock->method('isPreventSSOCustomersFromEditingAccountInformation')
            ->willReturn($isPreventSsoCustomersFromEditingAccountInformation);
        $layout->expects($shouldUnsetLinks ? static::exactly(2) : static::never())->method('unsetElement')
            ->withConsecutive(
                ['customer-account-navigation-address-link'],
                ['customer-account-navigation-account-edit-link']
            );
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @return \bool[][]|\string[][]
     */
    public function execute_ifPreconditionsAreMet_unsetsNavigationLinksProvider()
    {
        return array_merge(
            [
                'Happy path - all prerequisites fulfilled' => [
                    'full_action_name' => 'customer_account',
                    'is_prevent_sso_customers_from_editing_account_information' => true,
                    'is_bolt_sso_fs_enabled' => true,
                    'is_bolt_sso_config_enabled' => true,
                    'should_unset_links' => true
                ],
                'SSO feature switch disabled' => [
                    'full_action_name' => 'customer_account',
                    'is_prevent_sso_customers_from_editing_account_information' => true,
                    'is_bolt_sso_fs_enabled' => false,
                    'is_bolt_sso_config_enabled' => true,
                    'should_unset_links' => false
                ],
                'SSO config disabled' => [
                    'full_action_name' => 'customer_account',
                    'is_prevent_sso_customers_from_editing_account_information' => true,
                    'is_bolt_sso_fs_enabled' => true,
                    'is_bolt_sso_config_enabled' => false,
                    'should_unset_links' => false
                ],
                'SSO customers should be prevented from editing their account and address data feature switch disabled' => [
                    'full_action_name' => 'customer_account',
                    'is_prevent_sso_customers_from_editing_account_information' => false,
                    'is_bolt_sso_fs_enabled' => true,
                    'is_bolt_sso_config_enabled' => true,
                    'should_unset_links' => false
                ],
                'On checkout page' => [
                    'full_action_name' => 'checkout_index_index',
                    'is_prevent_sso_customers_from_editing_account_information' => true,
                    'is_bolt_sso_fs_enabled' => true,
                    'is_bolt_sso_config_enabled' => true,
                    'should_unset_links' => false
                ],
            ],
            array_map(function ($route) {
                return [
                    'full_action_name' => $route,
                    'is_prevent_sso_customers_from_editing_account_information' => true,
                    'is_bolt_sso_fs_enabled' => true,
                    'is_bolt_sso_config_enabled' => true,
                    'should_unset_links' => false
                ];
            }, ['checkout_cart_index', 'catalog_product_view', 'catalog_category_view', 'checkout_cart_index']),
            array_values(
                array_map(function ($flags) {
                    return [
                        'full_action_name' => 'customer_account',
                        'is_prevent_sso_customers_from_editing_account_information' => $flags[0],
                        'is_bolt_sso_fs_enabled' => $flags[1],
                        'is_bolt_sso_config_enabled' => $flags[2],
                        'should_unset_links' => $flags[0] && $flags[1] && $flags[2]
                    ];
                }, TestHelper::getAllBooleanCombinations(3))
            ),
        );
    }
    
}
