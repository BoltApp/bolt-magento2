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
namespace Bolt\Boltpay\Plugin;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\RequireJs\Config\File\Collector\Aggregated as RequireJsCollector;

/**
 * Class RequireJs
 * RequireJsCollector plugin.
 * Do not overwrite page cache javascript for Magento store versions >= 2.2.0.
 *
 * @package Bolt\Boltpay\Plugin
 */
class RequireJs
{
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * RequireJs constructor.
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ProductMetadataInterface $productMetadata
    ) {
        $this->productMetadata = $productMetadata;
    }

    /**
     * After requireJs files are collected remove the ones added by Bolt
     * for the store version greater than or equal to 2.2.0.
     *
     * @param RequireJsCollector $subject
     * @param array $files
     * @return array
     */
    public function afterGetFiles(RequireJsCollector $subject, array $files)
    {
        $magentoVersion = $this->productMetadata->getVersion();

        if (version_compare($magentoVersion, '2.2.0', '>=')) {

            foreach ($files as $index => $file) {

                if ($file->getModule() === 'Bolt_Boltpay') {
                    unset($files[$index]);
                }
            }
        }
        return $files;
    }
}
