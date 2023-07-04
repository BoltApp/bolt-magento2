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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\GetProductImageUrlInterface;
use Bolt\Boltpay\Helper\Bugsnag;
use Magento\Framework\App\Area;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class for fetching product image url
 */
class GetProductImageUrl implements GetProductImageUrlInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ImageHelper $imageHelper
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper,
        StoreManagerInterface $storeManager,
        Emulation $emulation,
        Bugsnag $bugsnag
    ) {
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
        $this->bugsnag = $bugsnag;
    }
    /**
     * @inheriDoc
     */
    public function execute(int $productId, string $imageId = self::DEFAULT_IMAGE_ID): string
    {
        try {
            $this->emulation->startEnvironmentEmulation($this->storeManager->getStore()->getId(), Area::AREA_FRONTEND, true);
            $product = $this->productRepository->getById($productId);
            $image = $this->imageHelper->init($product, $imageId)->getUrl();
            $this->emulation->stopEnvironmentEmulation();
            return $image;
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Product not found with given identifier.'));
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_INTERNAL_ERROR);
        }
    }
}
