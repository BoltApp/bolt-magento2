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

namespace Bolt\Boltpay\Setup\Patch\Data;

use Bolt\Boltpay\Helper\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

/**
 * Class DecryptSavedPublicKeys
 */
class DecryptSavedPublicKeys implements DataPatchInterface
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory
     */
    private $configDataCollectionFactory;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $resourceConfig;

    /**
     * UpdateAllowedMethods constructor.
     *
     * @param \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configDataCollectionFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface                  $encryptor
     * @param \Magento\Framework\App\Cache\TypeListInterface                    $cacheTypeList
     * @param \Magento\Config\Model\ResourceModel\Config                        $resourceConfig
     */
    public function __construct(
        \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configDataCollectionFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig
    ) {
        $this->configDataCollectionFactory = $configDataCollectionFactory;
        $this->encryptor = $encryptor;
        $this->cacheTypeList = $cacheTypeList;
        $this->resourceConfig = $resourceConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $publicKeyConfigs = $this->configDataCollectionFactory->create()
            ->addFieldToFilter(
                'path',
                [
                    'in' => [
                        Config::XML_PATH_PUBLISHABLE_KEY_BACK_OFFICE,
                        Config::XML_PATH_PUBLISHABLE_KEY_CHECKOUT,
                        Config::XML_PATH_PUBLISHABLE_KEY_PAYMENT,
                    ]
                ]
            );
        /** @var \Magento\Framework\App\Config\Value $publicKeyConfig */
        foreach ($publicKeyConfigs as $publicKeyConfig) {
            $value = $publicKeyConfig->getValue();
            try {
                $decryptedValue = $this->encryptor->decrypt($value);
                if ($value && $decryptedValue) {
                    $this->resourceConfig->saveConfig(
                        $publicKeyConfig->getPath() . '_plain',
                        $decryptedValue,
                        $publicKeyConfig->getScope(),
                        $publicKeyConfig->getScopeId()
                    );
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
