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

namespace Bolt\Boltpay\Test\Unit\Setup;

use Bolt\Boltpay\Model\ErrorResponse;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Setup\RecurringData;
use Bolt\Boltpay\Helper\FeatureSwitch\Manager;
use Bolt\Boltpay\Model\ErrorResponse as BoltErrorResponse;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Class RecurringDataTest
 * @coversDefaultClass \Bolt\Boltpay\Setup\RecurringData
 */
class RecurringDataTest extends TestCase
{
    /**
     * @var Manager
     */
    private $fsManager;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var BoltErrorResponse
     */
    private $errorResponse;

    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;

    /**
     * @var ModuleContextInterface
     */
    private $context;

    /**
     * @var RecurringData
     */
    private $currentMock;

    protected function setUp()
    {
        $this->fsManager = $this->createPartialMock(Manager::class, ['updateSwitchesFromBolt']);
        $this->logHelper = $this->createPartialMock(LogHelper::class, ['addInfoLog']);
        $this->metricsClient = $this->createPartialMock(MetricsClient::class, ['getCurrentTime', 'processMetric']);
        $this->errorResponse = $this->createPartialMock(ErrorResponse::class, ['prepareErrorMessage']);
        $this->setup = $this->createMock(ModuleDataSetupInterface::class);
        $this->context = $this->createMock(ModuleContextInterface::class);

        $this->currentMock = $this->getMockBuilder(RecurringData::class)
            ->setConstructorArgs([
                $this->fsManager,
                $this->logHelper,
                $this->metricsClient,
                $this->errorResponse
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @covers ::install
     * @test
     */
    public function install()
    {
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturnSelf();
        $this->fsManager->expects(self::once())->method('updateSwitchesFromBolt')->willReturnSelf();
        $this->currentMock->install($this->setup, $this->context);
    }

    /**
     * @covers ::install
     * @test
     */
    public function install_throwException()
    {
        $this->fsManager->expects(self::once())->method('updateSwitchesFromBolt')->willThrowException(new \Exception('Exception Message'));
        $this->errorResponse->expects(self::once())->method('prepareErrorMessage')->with(BoltErrorResponse::ERR_SERVICE, 'Exception Message')->willReturnSelf();
        $this->logHelper->expects(self::exactly(2))->method('addInfoLog')->willReturnSelf();
        $this->metricsClient->expects(self::once())->method('getCurrentTime')->willReturnSelf();
        $this->metricsClient->expects(self::once())->method('processMetric')->willReturnSelf();

        $this->currentMock->install($this->setup, $this->context);
    }
}
