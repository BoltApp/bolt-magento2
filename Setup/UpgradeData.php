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

namespace Bolt\Boltpay\Setup;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\BlockRepository;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ConfigInterface
     */
    private $configResource;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * Construct function.
     *
     * @param BlockFactory $blockFactory
     * @param BlockRepository $blockRepository
     * @param DirectoryList $directoryList
     * @param ConfigInterface $configResource
     * @param ScopeConfigInterface $config
     * @param EavSetupFactory $eavSetupFactory
     * @param CountryCurrencyHelperInterface $countryCurrencyHelper
     * @param Filesystem $filesystem
     * @param EavConfig $eavConfig
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ConfigInterface $configResource,
        ScopeConfigInterface $config,
        EavSetupFactory $eavSetupFactory,
        EavConfig $eavConfig,
        ProductMetadataInterface $productMetadata
    ) {
        $this->config = $config;
        $this->configResource = $configResource;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->productMetadata = $productMetadata;
    }

    /**
     * @inheritdoc
     *
     * @throws CouldNotDeleteException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws FileSystemException
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '2.24.3', '<')) {
            $this->createProductAttributesAndGroup($setup);
        }
        
        if (version_compare($context->getVersion(), '2.24.3', '<')) {
            $this->createCategoryAttributes($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createProductAttributesAndGroup(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $entityTypeId = $eavSetup->getEntityTypeId(Product::ENTITY);
        $attributeSetIds = $eavSetup->getAllAttributeSetIds($entityTypeId);
        $createdAttributeIds = $this->createProductAttributes($entityTypeId, $eavSetup);

        foreach ($attributeSetIds as $attributeSetId) {
            $eavSetup->addAttributeGroup(
                $entityTypeId,
                $attributeSetId,
                'Bolt Pay Options',
                80
            );
            $groupId = $eavSetup->getAttributeGroupId(
                $entityTypeId,
                $attributeSetId,
                'Bolt Pay Options'
            );
            foreach ($createdAttributeIds as $key => $attributeId) {
                $eavSetup->addAttributeToGroup(
                    Product::ENTITY,
                    $attributeSetId,
                    $groupId,
                    $attributeId,
                    $key + 1
                );
            }
        }
    }

    /**
     * @param $entityTypeId int
     * @param $eavSetup \Magento\Eav\Setup\EavSetup
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function createProductAttributes($entityTypeId, $eavSetup)
    {
        $createdAttributeIds = [];
        $attributes = [
            'bolt_shipment_type' => [
                'type'         => 'varchar',
                'label'        => __('Shipment Type'),
                'input'        => 'select',
                'required'     => false,
                'sort_order'   => 10,
                'user_defined' => 1,
                'global'       => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'source'       => \Bolt\Boltpay\Model\Config\Source\ShipmentType::class,
                'apply_to'     => '',
                'note'         => ''
            ],
        ];

        foreach ($attributes as $attributeCode => $attributeOptions) {
            $createdAttributeIds[] = $eavSetup
                ->addAttribute($entityTypeId, $attributeCode, $attributeOptions)
                ->getAttributeId($entityTypeId, $attributeCode);
        }

        return $createdAttributeIds;
    }
    
    /**
     * @param ModuleDataSetupInterface $setup
     */
    private function createCategoryAttributes(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Category::ENTITY,
            'bolt_shipment_type_cat',
            [
                'type'       => 'varchar',
                'label'      => __('Shipment Type'),
                'input'      => 'select',
                'required'   => false,
                'sort_order' => 10,
                'global'     => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'source'     => \Bolt\Boltpay\Model\Config\Source\ShipmentType::class,
                'group'      => __('General Information'),
            ]
        );
    }
}
