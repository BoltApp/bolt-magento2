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

namespace Bolt\Boltpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\ConfigFactory;
use Bolt\Boltpay\Model\Api\Data\AutomatedTesting\StoreItemFactory;

/**
 * Helper for automated testing
 */
class AutomatedTesting extends AbstractHelper
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var StoreItemFactory
     */
    private $storeItemFactory;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param Context $context
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigFactory $configFactory
     * @param StoreItemFactory $storeItemFactory
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Context $context,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ConfigFactory $configFactory,
        StoreItemFactory $storeItemFactory,
        Bugsnag $bugsnag
    ) {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->configFactory = $configFactory;
        $this->storeItemFactory = $storeItemFactory;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Generate and return automated testing config
     *
     * @return Config
     */
    public function getAutomatedTestingConfig()
    {
        $config = $this->configFactory->create();
        try {
            $simpleProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
            if ($simpleProduct === null) {
                return;
            }

            $virtualProduct = $this->getProduct(\Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL);

            $simpleStoreItem = $this->storeItemFactory->create()
                                                      ->setItemUrl($simpleProduct->getProductUrl())
                                                      ->setName(trim($simpleProduct->getName()))
                                                      ->setPrice('$' . number_format($simpleProduct->getPrice(), 2, '.', ''))
                                                      ->setType('simple');
            $virtualStoreItem = $virtualProduct === null
                ? null
                : $this->storeItemFactory->create()
                                         ->setItemUrl($virtualProduct->getProductUrl())
                                         ->setName(trim($virtualProduct->getName()))
                                         ->setPrice('$' . number_format($virtualProduct->getPrice(), 2, '.', ''))
                                         ->setType('virtual');

            $storeItems[] = $simpleStoreItem;
            if ($virtualStoreItem !== null) {
                $storeItems[] = $virtualStoreItem;
            }

            return $config->setStoreItems($storeItems);
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Return a product with the specified type
     *
     * @param string $type
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    private function getProduct($type)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('type_id', $type)
            ->create();

        $products = $this->productRepository
            ->getList($searchCriteria)
            ->getItems();

        return empty($products) ? null : reset($products);
    }
}