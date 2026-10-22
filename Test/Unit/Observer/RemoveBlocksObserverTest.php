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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.boltapp.com)
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
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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

    private $request;
    /**
     * @test
     */
    #[Test]
    public function execute_unsetsElements_ifBoltSSOEnabled()
    {
        $eventObserver = $this->createPartialMockWithMethods(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('customer_account_login');
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $layout->expects(static::exactly(3))->method('unsetElement')->with(...self::consecutiveArgs(
            ['customer_form_login'],
            ['customer.new'],
            ['customer_form_register']
        ));
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    #[Test]
    public function execute_doesNotUnsetElements_ifNotLoginOrRegisterPage()
    {
        $eventObserver = $this->createPartialMockWithMethods(Observer::class, ['getLayout', 'getData']);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('checkout_cart_index');
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getLayout');
        $layout->expects(static::never())->method('unsetElement');
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    #[Test]
    public function execute_doesNotUnsetElements_ifBoltSSONotDisabled()
    {
        $eventObserver = $this->createPartialMockWithMethods(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('customer_account_login');
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(false);
        $layout->expects(static::exactly(2))->method('unsetElement')->with(...self::consecutiveArgs(
            ['bolt_sso_login'],
            ['bolt_sso_register']
        ));
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    #[Test]
    public function execute_doesNotUnsetElements_ifBoltSSOEnabledButUseGetShowNativeParam()
    {
        $eventObserver = $this->createPartialMockWithMethods(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')->willReturn('customer_account_login');
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $this->request->expects(static::once())->method('getParam')->willReturn('native');
        $layout->expects(static::exactly(2))->method('unsetElement')->with(...self::consecutiveArgs(
            ['bolt_sso_login'],
            ['bolt_sso_register']
        ));
        $this->currentMock->execute($eventObserver);
    }

    protected function setUpInternal()
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->deciderMock = $this->createMock(Decider::class);
        $this->currentMock = new RemoveBlocksObserver($this->configHelper, $this->logHelper, $this->request, $this->deciderMock);
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
    #[Test]
    #[DataProvider('execute_ifPreconditionsAreMet_unsetsNavigationLinksProvider')]
    public function execute_ifPreconditionsAreMet_unsetsNavigationLinks(
        $fullActionName,
        $isPreventSsoCustomersFromEditingAccountInformation,
        $isBoltSsoFsEnabled,
        $isBoltSsoConfigEnabled,
        $shouldUnsetLinks
    ) {
        $eventObserver = $this->createPartialMockWithMethods(Observer::class, ['getLayout', 'getData']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getData')->with('full_action_name')
            ->willReturn($fullActionName);
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->method('isBoltSSOEnabled')->willReturn($isBoltSsoConfigEnabled);
        $this->deciderMock->method('isBoltSSOEnabled')->willReturn($isBoltSsoFsEnabled);
        $this->deciderMock->method('isPreventSSOCustomersFromEditingAccountInformation')
            ->willReturn($isPreventSsoCustomersFromEditingAccountInformation);
        $layout->expects($shouldUnsetLinks ? static::exactly(2) : static::never())->method('unsetElement')
            ->with(...self::consecutiveArgs(
                ['customer-account-navigation-address-link'],
                ['customer-account-navigation-account-edit-link']
            ));
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @return \bool[][]|\string[][]
     */
    public static function execute_ifPreconditionsAreMet_unsetsNavigationLinksProvider()
    {
        return array_merge(
            [
                'Happy path - all prerequisites fulfilled' => [
                    'fullActionName' => 'customer_account',
                    'isPreventSsoCustomersFromEditingAccountInformation' => true,
                    'isBoltSsoFsEnabled' => true,
                    'isBoltSsoConfigEnabled' => true,
                    'shouldUnsetLinks' => true
                ],
                'SSO feature switch disabled' => [
                    'fullActionName' => 'customer_account',
                    'isPreventSsoCustomersFromEditingAccountInformation' => true,
                    'isBoltSsoFsEnabled' => false,
                    'isBoltSsoConfigEnabled' => true,
                    'shouldUnsetLinks' => false
                ],
                'SSO config disabled' => [
                    'fullActionName' => 'customer_account',
                    'isPreventSsoCustomersFromEditingAccountInformation' => true,
                    'isBoltSsoFsEnabled' => true,
                    'isBoltSsoConfigEnabled' => false,
                    'shouldUnsetLinks' => false
                ],
                'SSO customers should be prevented from editing their account and address data feature switch disabled' => [
                    'fullActionName' => 'customer_account',
                    'isPreventSsoCustomersFromEditingAccountInformation' => false,
                    'isBoltSsoFsEnabled' => true,
                    'isBoltSsoConfigEnabled' => true,
                    'shouldUnsetLinks' => false
                ],
                'On checkout page' => [
                    'fullActionName' => 'checkout_index_index',
                    'isPreventSsoCustomersFromEditingAccountInformation' => true,
                    'isBoltSsoFsEnabled' => true,
                    'isBoltSsoConfigEnabled' => true,
                    'shouldUnsetLinks' => false
                ],
            ],
            array_map(
                function ($route) {
                    return [
                        'fullActionName' => $route,
                        'isPreventSsoCustomersFromEditingAccountInformation' => true,
                        'isBoltSsoFsEnabled' => true,
                        'isBoltSsoConfigEnabled' => true,
                        'shouldUnsetLinks' => false
                    ];
                },
                ['checkout_cart_index', 'catalog_product_view', 'catalog_category_view', 'checkout_cart_index']
            ),
            array_values(
                array_map(
                    function ($flags) {
                        return [
                            'fullActionName' => 'customer_account',
                            'isPreventSsoCustomersFromEditingAccountInformation' => $flags[0],
                            'isBoltSsoFsEnabled' => $flags[1],
                            'isBoltSsoConfigEnabled' => $flags[2],
                            'shouldUnsetLinks' => $flags[0] && $flags[1] && $flags[2]
                        ];
                    },
                    TestHelper::getAllBooleanCombinations(3)
                )
            )
        );
    }
}
