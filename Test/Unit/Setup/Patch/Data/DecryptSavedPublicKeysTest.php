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

namespace Bolt\Boltpay\Test\Unit\Setup\Patch\Data;

use Bolt\Boltpay\Helper\Config;
use Bolt\Boltpay\Setup\Patch\Data\DecryptSavedPublicKeys;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\ScopeInterface;

/**
 * Class DecryptSavedPublicKeysTest
 *
 * @coversDefaultClass \Bolt\Boltpay\Setup\Patch\Data\DecryptSavedPublicKeys
 */
class DecryptSavedPublicKeysTest extends \Bolt\Boltpay\Test\Unit\BoltTestCase
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
    }

    /**
     * @test
     * that apply decrypts all saved public keys in a separate, plain field
     *
     * @covers ::apply
     */
    public function apply_always_decryptsSavedPublicKeys()
    {
        $key = md5('bolt');
        $encryptor = $this->objectManager->get(Encryptor::class);
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
        $resourceConfig = $this->objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $encryptedKey = $encryptor->encrypt($key);

        $config = [];
        foreach ([
            Config::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE,
            Config::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
            Config::XML_PATH_PUBLISHABLE_KEY_PAYMENT
        ] as $path) {
            foreach ([ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ScopeInterface::SCOPE_WEBSITE] as $scope) {
                foreach ([0, 1] as $scopeId) {
                    $config[] = [
                        'path'    => $path,
                        'value'   => $encryptedKey,
                        'scope'   => $scope,
                        'scopeId' => $scopeId
                    ];
                }
            }
        }
        TestUtils::setupBoltConfig($config);
        $this->objectManager->get(DecryptSavedPublicKeys::class)->apply();
        $scopeConfig->clean();
        foreach ($config as $item) {
            static::assertEquals(
                $key,
                $scopeConfig->getValue($item['path'] . '_plain', $item['scope'], $item['scopeId'])
            );
        }
    }
}
