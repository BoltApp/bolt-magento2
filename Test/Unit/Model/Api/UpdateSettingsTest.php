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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Hook;
use Bolt\Boltpay\Model\Api\UpdateSettings;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Bolt\Boltpay\Test\Unit\TestHelper;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Class UpdateSettingsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\UpdateSettings
 */
class UpdateSettingsTest extends BoltTestCase
{
    const TRACE_ID_HEADER = 'KdekiEGku3j1mU21Mnsx5g==';
    const SECRET = '42425f51e0614482e17b6e913d74788eedb082e2e6f8067330b98ffa99adc809';
    const APIKEY = '3c2d5104e7f9d99b66e1c9c550f6566677bf81de0d6f25e121fdb57e47c2eafc';

    /**
     * @var UpdateSettings
     */
    private $updateSettings;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var int
     */
    private $websiteId;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var Request
     */
    private $request;

    /**
     * @inheritdoc
     */
    public function setUpInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }
        $this->objectManager = Bootstrap::getObjectManager();
        $this->updateSettings = $this->objectManager->create(UpdateSettings::class);
        $this->resetRequest();
        $this->resetResponse();

        $store = $this->objectManager->get(StoreManagerInterface::class);
        $this->storeId = $store->getStore()->getId();

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $this->websiteId = $websiteRepository->get('base')->getId();

        $encryptor = $this->objectManager->get(EncryptorInterface::class);
        $secret = $encryptor->encrypt(self::SECRET);
        $apikey = $encryptor->encrypt(self::APIKEY);

        $configData = [
            [
                'path'    => 'payment/boltpay/signing_secret',
                'value'   => $secret,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path'    => 'payment/boltpay/api_key',
                'value'   => $apikey,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
            [
                'path'    => 'payment/boltpay/active',
                'value'   => 1,
                'scope'   => ScopeInterface::SCOPE_STORE,
                'scopeId' => $this->storeId,
            ],
        ];
        TestUtils::setupBoltConfig($configData);
    }

    /**
     * Request getter
     *
     * @param array $bodyParams
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    public function createRequest($bodyParams)
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }

        $requestContent = json_encode($bodyParams);

        $computed_signature = base64_encode(hash_hmac('sha256', $requestContent, self::SECRET, true));

        $this->request->getHeaders()->clearHeaders();
        $this->request->getHeaders()->addHeaderLine('X-Bolt-Hmac-Sha256', $computed_signature);
        $this->request->getHeaders()->addHeaderLine('X-bolt-trace-id', self::TRACE_ID_HEADER);
        $this->request->getHeaders()->addHeaderLine('Content-Type', 'application/json');

        $this->request->setParams($bodyParams);
        $this->request->setContent($requestContent);
        $this->request->setMethod("POST");

        return $this->request;
    }

    protected function tearDownInternal()
    {
        if (!class_exists('\Magento\TestFramework\Helper\Bootstrap')) {
            return;
        }

        $this->resetRequest();
        $this->resetResponse();
    }

    public function resetRequest()
    {
        if (!$this->request) {
            $this->request = $this->objectManager->get(Request::class);
        }

        $this->objectManager->removeSharedInstance(Request::class);
        $this->request = null;
    }

    public function resetResponse()
    {
        if (!$this->response) {
            $this->response = $this->objectManager->get(Response::class);
        }

        $this->objectManager->removeSharedInstance(Response::class);
        $this->response = null;
    }

    /**
     * @test
     * ignore set config setting if the setting name is api_key or signing_secret
     * @covers ::execute
     */
    public function update_settings_ignoreSetConfigSetting()
    {
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $orderHelperProperty = new \ReflectionProperty(
            UpdateSettings::class,
            'hookHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($this->updateSettings, $hookHelper);

        $mockDebugInfo = json_encode([
            'division' => [
                'pluginIntegrationInfo' => [
                    'phpVersion' => PHP_VERSION,
                    'platformVersion' => '2.3.3',
                    'pluginConfigSettings' => [
                        [
                            'name' => 'api_key',
                            'value' => 'test_key'
                        ],
                        [
                            'name' => 'signing_secret',
                            'value' => 'test_key'
                        ]
                    ]
                ]
            ]
        ]);
        $this->updateSettings->execute($mockDebugInfo);
        /** @var ConfigHelper $configHelper */
        $configHelper = $this->objectManager->create(ConfigHelper::class);
        $apiKey = $configHelper->getApiKey(0);
        $signingSecret = $configHelper->getSigningSecret(0);

        $this->assertEquals(null, $apiKey);
        $this->assertEquals(null, $signingSecret);

        $response = TestHelper::getProperty($this->updateSettings, 'response');
        $this->assertEquals(
            200,
            $response->getHttpResponseCode()
        );
    }

    /**
     * @test
     *
     * @covers ::execute
     */
    public function update_settings_throwApiException()
    {
        $mockDebugInfo = json_encode([
            'division' => [
                'pluginIntegrationInfo' => [
                    'phpVersion' => PHP_VERSION,
                    'platformVersion' => '2.3.3',
                    'pluginConfigSettings' => [
                        [
                            'name' => 'api_key',
                            'value' => 'test_key'
                        ],
                        [
                            'name' => 'signing_secret',
                            'value' => 'test_key'
                        ]
                    ]
                ]
            ]
        ]);
        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Precondition Failed');
        $this->updateSettings->execute($mockDebugInfo);
    }

    /**
     * @test
     *
     * @covers ::execute
     */
    public function update_settings_successful()
    {
        $this->createRequest([]);
        $hookHelper = $this->objectManager->create(Hook::class);
        $stubApiHelper = new stubBoltApiHelper();
        $apiHelperProperty = new \ReflectionProperty(
            Hook::class,
            'apiHelper'
        );
        $apiHelperProperty->setAccessible(true);
        $apiHelperProperty->setValue($hookHelper, $stubApiHelper);

        $orderHelperProperty = new \ReflectionProperty(
            UpdateSettings::class,
            'hookHelper'
        );
        $orderHelperProperty->setAccessible(true);
        $orderHelperProperty->setValue($this->updateSettings, $hookHelper);

        $mockDebugInfo = json_encode([
            'division' => [
                'pluginIntegrationInfo' => [
                    'phpVersion' => PHP_VERSION,
                    'platformVersion' => '2.3.3',
                    'pluginConfigSettings' => [
                        [
                            'name' => 'publishable_key_checkout',
                            'value' => 'test_key'
                        ],
                    ]
                ]
            ]
        ]);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects(self::once())->method('setConfigSetting')->with(
            'publishable_key_checkout',
            'test_key'
        );

        TestHelper::setProperty($this->updateSettings, 'configHelper', $configHelper);
        $this->updateSettings->execute($mockDebugInfo);

        $response = TestHelper::getProperty($this->updateSettings, 'response');
        $this->assertEquals(
            200,
            $response->getHttpResponseCode()
        );
    }
}
