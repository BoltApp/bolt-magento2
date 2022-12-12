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

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Test\Unit\TestUtils;
use Magento\Framework\App\ResourceConnection;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Bolt\Boltpay\Model\Api\GetProductImageUrl;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * @coversDefaultClass \Bolt\Boltpay\Model\Api\GetProductImageUrl
 */
class GetProductImageUrlTest extends BoltTestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var GetProductImageUrl
     */
    private $getProductImageUrl;

    /**
     * @var Config
     */
    private $mediaConfig;

    /**
     * @var WriteInterface
     */
    private $mediaDirectory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @inheritDoc
     */
    protected function setUpInternal()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->getProductImageUrl = $this->objectManager->create(GetProductImageUrl::class);
        $this->resource = $this->objectManager->get(ResourceConnection::class);
        $this->configHelper = $this->objectManager->get(ConfigHelper::class);

        /** @var $mediaConfig Config */
        $this->mediaConfig = $this->objectManager->create(Config::class);

        /** @var $mediaDirectory WriteInterface */
        $this->mediaDirectory = $this->objectManager->create(Filesystem::class)
            ->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @inheritDoc
     */
    protected function tearDownInternal()
    {
        $connection = $this->resource->getConnection('default');
        $connection->delete($connection->getTableName('catalog_product_entity'));
        $connection->delete($connection->getTableName('url_rewrite'), ['entity_type = ?' => 'product']);
        $this->mediaDirectory->delete($this->mediaConfig->getBaseMediaPath());
        $this->mediaDirectory->delete($this->mediaConfig->getBaseTmpMediaPath());
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_successProductImageUrl()
    {
        $product = $this->createSimpleProductWithImage();
        $imageUrl = $this->getProductImageUrl->execute($product->getId());
        $image = str_replace('/', '\/', $product->getImage());
        $this->assertMatchesRegularExpression(
            "/https?:\/\/localhost\/(pub\/)?media\/catalog\/product\/cache\/[a-f0-9]{32}".$image."/",
            $imageUrl
        );
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_exceptionWrongProductId()
    {
        $this->expectExceptionMessage('Product not found with given identifier.');
        $this->getProductImageUrl->execute(23);
    }

    /**
     * @test
     * @covers ::execute
     */
    public function execute_exceptionWrongImageId()
    {
        $product = $this->createSimpleProductWithImage();
        $imageUrl = $this->getProductImageUrl->execute($product->getId(), 'wrong_id');
        //in magento 2.2.* with wrong image id type magento returns default image type
        if (version_compare($this->configHelper->getStoreVersion(), '2.3.0', '<')) {
            $image = str_replace('/', '\/', $product->getImage());
            $this->assertMatchesRegularExpression(
                "/https?:\/\/localhost\/(pub\/)?media\/catalog\/product\/cache\/[a-f0-9]{32}".$image."/",
                $imageUrl
            );
        } else {
            $this->assertStringContainsString('placeholder', $imageUrl);
        }
    }

    /**
     * Creates product with image
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function createSimpleProductWithImage()
    {
        $product = TestUtils::createSimpleProduct();
        $targetDirPath = $this->mediaConfig->getBaseMediaPath();
        $targetTmpDirPath = $this->mediaConfig->getBaseTmpMediaPath();

        $this->mediaDirectory->create($targetDirPath);
        $this->mediaDirectory->create($targetTmpDirPath);

        $dist = $this->mediaDirectory->getAbsolutePath($this->mediaConfig->getBaseMediaPath() .  DIRECTORY_SEPARATOR . 'magento_image.jpg');
        $this->mediaDirectory->getDriver()->filePutContents($dist, file_get_contents(__DIR__ . '/_files/magento_image.jpg'));

        $path = $this->mediaConfig->getBaseMediaPath() . '/magento_image.jpg';
        $absolutePath = $this->mediaDirectory->getAbsolutePath() . $path;

        $product->addImageToMediaGallery(
            $absolutePath,
            [
                'image',
                'small_image',
                'thumbnail',
            ],
            false,
            false
        );

        $product->setStoreId(1)
            ->setImage('/m/a/magento_image.jpg')
            ->setSmallImage('/m/a/magento_image.jpg')
            ->setThumbnail('/m/a/magento_image.jpg')
            ->setData('media_gallery', ['images' => [
                [
                    'file' => '/m/a/magento_image.jpg',
                    'position' => 1,
                    'label' => 'Image Alt Text',
                    'disabled' => 0,
                    'media_type' => 'image'
                ],
            ]])
            ->setCanSaveCustomOptions(true);
        $this->objectManager->removeSharedInstance(\Magento\Framework\Config\View::class);
        $this->objectManager->removeSharedInstance(\Magento\Catalog\Helper\Image::class);
        return $product->save();
    }
}
