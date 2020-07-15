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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Helper\Log as LogHelper;

class ThirdPartyModuleFactory
{
    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $_moduleManager;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var LogHelper
     */
    private $logHelper;

    private $moduleName;

    private $className;

    /**
     * ThirdPartyModuleFactory constructor.
     *
     * @param \Magento\Framework\Module\Manager         $moduleManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param LogHelper                                 $logHelper
     * @param null                                      $moduleName
     * @param null                                      $className
     */
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        LogHelper $logHelper,
        $moduleName = null,
        $className = null
    ) {
        $this->_moduleManager = $moduleManager;
        $this->_objectManager = $objectManager;
        $this->moduleName = $moduleName;
        $this->className = $className;
        $this->logHelper = $logHelper;
    }

    /**
     * Check whether the module is available (installed and enabled)
     * @return bool
     */
    public function isAvailable()
    {
        return $this->_moduleManager->isEnabled($this->moduleName);
    }
    
    /**
     * Check whether the class exists
     * @return bool
     */
    public function isExists()
    {
        return class_exists($this->className);
    }

    /**
     * @param array $data
     * @return mixed|null
     */
    public function getInstance(array $data = [])
    {
        if ($this->isAvailable() && $this->isExists()) {
            $this->logHelper->addInfoLog('# Module is Enabled: ' . $this->moduleName);
            $this->logHelper->addInfoLog('# Class: ' . $this->className);
            return $this->_objectManager->create($this->className, $data);
        }
        $this->logHelper->addInfoLog('# Module is Disabled or not Found: ' . $this->moduleName);

        return null;
    }
}
