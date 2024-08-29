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
 * @copyright  Copyright (c) 2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Helper\Geolocation;
use Magento\Framework\App\Helper\Context;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Model\HttpClientAdapterFactory;
use Bolt\Boltpay\Model\HttpClientAdapter;
use Magento\Framework\App\CacheInterface;

/**
 * Class GeolocationTest
 * @package Bolt\Boltpay\Test\Unit\Helper
 * @coversDefaultClass \Bolt\Boltpay\Helper\Geolocation
 */
class GeolocationTest extends BoltTestCase
{
    const API_KEY = 'api_key';
    const CLIENT_IP = '127.0.0.1';
    const LOCATION_JSON = '{test:test}';

    /**
     * @var Geolocation
     */
    protected $mock;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var HttpClientAdapterFactory
     */
    private $httpClientAdapterFactory;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Context
     */
    private $context;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        $this->context = $this->createPartialMock(Context::class, ['getMpGiftCards']);
        $this->configHelper = $this->createPartialMock(ConfigHelper::class, ['getGeolocationApiKey', 'getClientIp']);
        $this->bugsnag = $this->createPartialMock(Bugsnag::class, ['notifyException']);
        $this->httpClientAdapterFactory = $this->createPartialMock(HttpClientAdapterFactory::class, ['create', 'setUri', 'setConfig', 'request', 'getBody']);
        $this->cache = $this->createPartialMock(CacheInterface::class, ['save', 'load', 'getFrontend', 'remove', 'clean']);
        $this->mock = $this->getMockBuilder(Geolocation::class)
            ->setMethods(['getLocationJson'])
            ->setConstructorArgs(
                [
                    $this->context,
                    $this->configHelper,
                    $this->bugsnag,
                    $this->httpClientAdapterFactory,
                    $this->cache
                ]
            )
            ->getMock();
    }

    /**
     * @test
     * that constructor sets internal properties
     *
     * @covers ::__construct
     */
    public function constructor_always_setsInternalProperties()
    {
        $instance = new Geolocation(
            $this->context,
            $this->configHelper,
            $this->bugsnag,
            $this->httpClientAdapterFactory,
            $this->cache
        );

        static::assertAttributeEquals($this->configHelper, 'configHelper', $instance);
        static::assertAttributeEquals($this->bugsnag, 'bugsnag', $instance);
        static::assertAttributeEquals($this->httpClientAdapterFactory, 'httpClientAdapterFactory', $instance);
        static::assertAttributeEquals($this->cache, 'cache', $instance);
    }

    /**
     * @test
     */
    public function getLocationJson_withEmptyApiKey()
    {
        $this->configHelper->expects(self::once())->method('getGeolocationApiKey')->willReturn(null);
        $this->assertNull($this->mock->getLocation());
    }

    /**
     * @test
     */
    public function getLocationJson_withLocationIsGetFromCache()
    {
        $this->configHelper->expects(self::once())->method('getGeolocationApiKey')->willReturn(self::API_KEY);
        $this->configHelper->expects(self::once())->method('getClientIp')->willReturn(self::CLIENT_IP);
        $this->cache->expects(self::once())->method('load')->willReturn(self::LOCATION_JSON);
        $this->assertEquals(self::LOCATION_JSON, $this->mock->getLocation());
    }

    /**
     * @test
     */
    public function getLocationJson_withLocationIsNotGetFromCache()
    {
        $this->configHelper->expects(self::once())->method('getGeolocationApiKey')->willReturn(self::API_KEY);
        $this->configHelper->expects(self::once())->method('getClientIp')->willReturn(self::CLIENT_IP);
        $this->cache->expects(self::once())->method('load')->willReturn(null);

        $this->httpClientAdapterFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('setUri')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('setConfig')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('request')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('getBody')->willReturn(self::LOCATION_JSON);

        $this->cache->expects(self::once())
            ->method('save')
            ->willReturn(self::LOCATION_JSON);

        $this->assertEquals(self::LOCATION_JSON, $this->mock->getLocation());
    }

    /**
     * @test
     */
    public function getLocationJson_throwException()
    {
        $this->configHelper->expects(self::once())->method('getGeolocationApiKey')->willReturn(self::API_KEY);
        $this->configHelper->expects(self::once())->method('getClientIp')->willReturn(self::CLIENT_IP);
        $this->cache->expects(self::once())->method('load')->willReturn(null);

        $this->httpClientAdapterFactory->expects(self::once())->method('create')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('setUri')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('setConfig')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('request')->willReturnSelf();
        $this->httpClientAdapterFactory->expects(self::once())->method('getBody')->willThrowException(new \Exception('exception'));
        $this->bugsnag->expects(self::once())->method('notifyException')->with(new \Exception('exception'))->willReturnSelf();

        $this->assertNull($this->mock->getLocation());
    }
}
