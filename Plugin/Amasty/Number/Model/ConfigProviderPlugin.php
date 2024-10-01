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
 * @copyright  Copyright (c) 2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Amasty\Number\Model;

use Magento\Framework\App;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProviderPlugin
{
    /**
     * @var int
     */
    private $storeId;
    
    /**
     * @var App\State
     */
    private $state;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var Closure proxy
     */
    private $methodCaller;
    
    public function __construct(
        App\State $state,
        StoreManagerInterface $storeManager
    ) {
        $this->state = $state;
        $this->storeManager = $storeManager;
        // Call protected method with a Closure proxy
        $this->methodCaller = function ($methodName, ...$params) {
            return $this->$methodName(...$params);
        }; 
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return string
     */
    public function afterGetNumberFormat(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if ($type !== \Amasty\Number\Model\ConfigProvider::ORDER_TYPE && $subject->isFormatSameAsOrder($type)) {
            return $result;
        }

        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_NUMBER_FORMAT;
        return $this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return bool
     */
    public function afterIsFormatSameAsOrder(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_NUMBER_SAME;
        return !!$this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return string
     */
    public function afterGetNumberPrefix(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_NUMBER_PREFIX;
        return (string)$this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return string
     */
    public function afterGetNumberReplacePrefix(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_NUMBER_PREFIX_REPLACE;
        return (string)$this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return int
     */
    public function afterGetStartCounterFrom(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_COUNTER_FROM;
        $start = (int)$this->getScopedValue($subject, $path);

        if ($start <= 0) {
            $start = \Amasty\Number\Model\ConfigProvider::DEFAULT_COUNTER_STEP;
        }

        return $start;
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return int
     */
    public function afterGetCounterStep(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_INCREMENT_STEP;
        $step = (int)$this->getScopedValue($subject, $path);

        if ($step <= 0) {
            $step = \Amasty\Number\Model\ConfigProvider::DEFAULT_COUNTER_STEP;
        }

        return $step;
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return int
     */
    public function afterGetCounterPadding(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_COUNTER_PAD;
        return (int)$this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param string $result
     * @param string $type
     *
     * @return string
     */
    public function afterGetCounterResetOnDateChange(\Amasty\Number\Model\ConfigProvider $subject, $result, $type)
    {
        if (!$this->isBoltAPIRest()) {
            return $result;
        }
        
        $path = $type . \Amasty\Number\Model\ConfigProvider::PART_COUNTER_RESET_DATE;
        return (string)$this->getScopedValue($subject, $path);
    }
    
    /**
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param null $result
     * @param string|int $storeId
     *
     */
    public function afterSetStoreId(\Amasty\Number\Model\ConfigProvider $subject, $result, $storeId)
    {
        $this->storeId = (int)$storeId;
    }
    
    /**
     * Get scope data on admin within defined storeID via setStoreId() method.
     * Counter number config must have correct scope during Order placement or Invoice/Shipping/Memo creating on admin
     * because scope config could not be automatically resolved on admin area.
     *
     * @param \Amasty\Number\Model\ConfigProvider $subject
     * @param $path
     * @return mixed
     */
    private function getScopedValue($subject, $path)
    {
        try {
            $storeValue = $this->methodCaller->call(
                $subject,
                'getValue',
                $path,
                $this->storeManager->getStore($this->storeId)->getId(),
                ScopeInterface::SCOPE_STORE
            );
            $websiteValue = $this->methodCaller->call(
                $subject,
                'getValue',
                $path,
                $this->storeManager->getStore($this->storeId)->getWebsiteId(),
                ScopeInterface::SCOPE_WEBSITE
            );
        } catch (\Throwable $e) {
            null;
        }

        return $storeValue ?? $websiteValue ?? $this->methodCaller->call(
                $subject,
                'getValue',
                $path
            );
    }
    
    /**
     * @return bool
     */
    private function isBoltAPIRest()
    {
        try {
            return ($this->state->getAreaCode() === \Magento\Framework\App\Area::AREA_WEBAPI_REST);
        } catch (\Throwable $e) {
            null;
        }

        return false;
    }
}