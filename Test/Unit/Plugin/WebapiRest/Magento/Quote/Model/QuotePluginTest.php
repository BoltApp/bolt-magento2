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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Test\Unit\Plugin\WebapiRest\Magento\Quote\Model;

/**
 * Test for {@see \Bolt\Boltpay\Plugin\WebapiRest\Magento\Quote\Model\QuotePlugin}
 *
 * @coversDefaultClass \Bolt\Boltpay\Plugin\WebapiRest\Magento\Quote\Model\QuotePlugin
 */
class QuotePluginTest extends \Bolt\Boltpay\Test\Unit\BoltTestCase
{

    /**
     * @var \Bolt\Boltpay\Helper\FeatureSwitch\Decider|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $featureSwitchesMock;

    /**
     * @var \Magento\Framework\App\State|\PHPUnit\Framework\MockObject\MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateMock;

    /**
     * Setup test dependencies
     */
    protected function setUp(): void
    {
        $this->featureSwitchesMock = $this->createMock(\Bolt\Boltpay\Helper\FeatureSwitch\Decider::class);
        $this->stateMock = $this->createMock(\Magento\Framework\App\State::class);
        parent::setUp();
    }

    /**
     * @test
     * that beforeSetData prevents setting Bolt as remote IP
     *
     * @covers ::beforeSetData
     *
     * @dataProvider beforeSetData_ifPreconditionsAreMetProvider
     */
    public function beforeSetData_ifPreconditionsAreMet_preventsSettingBoltIP(
        $key,
        $value,
        $originalRemoteIp,
        $currentRemoteIp,
        $isWebApiRest,
        $fromBolt,
        $isPreventSettingBoltIpsAsCustomerIpOnQuote,
        $changedArguments
    ) {
        $om = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->featureSwitchesMock->method('isPreventSettingBoltIpsAsCustomerIpOnQuote')
            ->willReturn($isPreventSettingBoltIpsAsCustomerIpOnQuote);
        if ($isWebApiRest) {
            $this->stateMock->method('getAreaCode')
                ->willReturn(\Magento\Framework\App\Area::AREA_WEBAPI_REST);
        } else {
            $this->stateMock->method('getAreaCode')
                ->willReturn(\Magento\Framework\App\Area::AREA_FRONTEND);
        }

        $pluginInstance = $om->create(
            \Bolt\Boltpay\Plugin\WebapiRest\Magento\Quote\Model\QuotePlugin::class,
            [
                'featureSwitches' => $this->featureSwitchesMock,
                'appState' => $this->stateMock
            ]
        );
        $quote = $om->create(\Magento\Quote\Model\Quote::class);
        $quote->setOrigData('remote_ip', $originalRemoteIp);
        $quote->setData('remote_ip', $currentRemoteIp);
        \Bolt\Boltpay\Helper\Hook::$fromBolt = $fromBolt;
        static::assertEquals($changedArguments, $pluginInstance->beforeSetData($quote, $key, $value));
    }

    /**
     * Data provider for {@see beforeSetData_ifPreconditionsAreMet_preventsSettingBoltIP}
     *
     * @return array[]
     */
    public function beforeSetData_ifPreconditionsAreMetProvider()
    {
        $boltIP = '52.53.112.98';
        $customerIP1 = '127.0.0.1';
        $customerIP2 = '172.20.10.1';
        return [
            'Happy path' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $boltIP,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP1,
                'isWebApiRest'                               => true,
                'fromBolt'                                   => true,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => true,
                'changedArguments'                           => ['remote_ip', $customerIP1],
            ],
            'Initial setting of the IP' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $customerIP1,
                'originalRemoteIp'                           => null,
                'currentRemoteIp'                            => null,
                'isWebApiRest'                               => true,
                'fromBolt'                                   => true,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => true,
                'changedArguments'                           => ['remote_ip', $customerIP1],
            ],
            'Read IP from Current Data' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $boltIP,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP2,
                'isWebApiRest'                               => true,
                'fromBolt'                                   => true,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => true,
                'changedArguments'                           => ['remote_ip', $customerIP2],
            ],
            'Read IP from Original Data' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $boltIP,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => null,
                'isWebApiRest'                               => true,
                'fromBolt'                                   => true,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => true,
                'changedArguments'                           => ['remote_ip', $customerIP1],
            ],
            'Non IP data change - nothing happens' => [
                'key'                                        => 'test',
                'value'                                      => true,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP2,
                'isWebApiRest'                               => false,
                'fromBolt'                                   => false,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => false,
                'changedArguments'                           => null,
            ],
            'Key is array - nothing happens' => [
                'key'                                        => ['test' => 1, 'test2' => 2],
                'value'                                      => true,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP2,
                'isWebApiRest'                               => false,
                'fromBolt'                                   => false,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => false,
                'changedArguments'                           => null,
            ],
            'Hook not from Bolt - nothing happens' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $boltIP,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP1,
                'isWebApiRest'                               => false,
                'fromBolt'                                   => false,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => true,
                'changedArguments'                           => null,
            ],
            'FS disabled - nothing happens' => [
                'key'                                        => 'remote_ip',
                'value'                                      => $boltIP,
                'originalRemoteIp'                           => $customerIP1,
                'currentRemoteIp'                            => $customerIP1,
                'isWebApiRest'                               => true,
                'fromBolt'                                   => true,
                'isPreventSettingBoltIpsAsCustomerIpOnQuote' => false,
                'changedArguments'                           => null,
            ],
        ];
    }
}
