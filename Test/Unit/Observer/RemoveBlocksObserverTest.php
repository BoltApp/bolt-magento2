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
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Observer\RemoveBlocksObserver;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
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
     * @test
     */
    public function execute_unsetsElements_ifBoltSSOEnabled()
    {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(true);
        $layout->expects(static::exactly(3))->method('unsetElement')->withConsecutive(
            ['register-link'],
            ['authorization-link'],
            ['authorization-link-login']
        );
        $this->currentMock->execute($eventObserver);
    }

    /**
     * @test
     */
    public function execute_doesNotUnsetElements_ifBoltSSONotEnabled()
    {
        $eventObserver = $this->createPartialMock(Observer::class, ['getLayout']);
        $layout = $this->createMock(Layout::class);
        $eventObserver->expects(static::once())->method('getLayout')->willReturn($layout);
        $this->configHelper->expects(static::once())->method('isBoltSSOEnabled')->willReturn(false);
        $layout->expects(static::never())->method('unsetElement');
        $this->currentMock->execute($eventObserver);
    }

    protected function setUpInternal()
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->currentMock = new RemoveBlocksObserver($this->configHelper, $this->logHelper);
    }
}
