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

namespace Bolt\Boltpay\Model\CatalogIngestion;

use Bolt\Boltpay\Model\ResourceModel\CatalogIngestion\ProductEvent\Collection as ProductEventCollection;
use Magento\Cron\Model\ResourceModel\Schedule\Collection as CronCollection;

/**
 * Debug class, generating debug data of catalog ingestion
 */
class Debug
{
    private const CRON_JOB_LOG_SIZE = 5;

    /**
     * @var ProductEventCollection
     */
    private $productEventCollection;

    /**
     * @var CronCollection
     */
    private $cronCollection;

    /**
     * @param ProductEventCollection $productEventCollection
     * @param CronCollection $cronCollection
     */
    public function __construct(
        ProductEventCollection $productEventCollection,
        CronCollection $cronCollection
    ) {
        $this->productEventCollection = $productEventCollection;
        $this->cronCollection = $cronCollection;
    }

    /**
     * Returns current product event database rows count
     *
     * @return int
     */
   public function getProductEventRowsCount(): int
   {
       return $this->productEventCollection->count();
   }

    /**
     * Returns cronjob's log related to 'bolt_catalog_ingestion'
     *
     * @return array
     */
   public function getCronJobLog(): array
   {
       $this->cronCollection->addFieldToFilter('job_code', ['eq'=> 'bolt_catalog_ingestion'])
           ->setOrder('schedule_id', 'DESC')
           ->setPageSize(self::CRON_JOB_LOG_SIZE)
           ->setCurPage(1);

       if (!$this->cronCollection->count()) {
           return [];
       }
       $result = [];
       foreach ($this->cronCollection->getItems() as $cronJob) {
           $result[] = $cronJob->getData();
       }
       return $result;
   }

    /**
     * Returns full debug catalog ingestion data as array
     *
     * @return array
     */
   public function getDebugFullData(): array
   {
       return [
           'product_event_rows_count' => $this->getProductEventRowsCount(),
           'cron_log' => $this->getCronJobLog()
       ];
   }
}
