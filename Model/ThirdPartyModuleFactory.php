<?php

namespace Bolt\Boltpay\Model;

class ThirdPartyModuleFactory
{
    private $instanceName;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $_moduleManager;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * ThirdPartyModuleFactory constructor.
     *
     * @param \Magento\Framework\Module\Manager         $moduleManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param null                                      $instanceName
     */
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = null
    ) {
        $this->_moduleManager = $moduleManager;
        $this->_objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * @param array $data
     * @return mixed|null
     */
    public function getInstance(array $data = array())
    {
        if ($this->_moduleManager->isEnabled($this->instanceName)) {
            return $this->_objectManager->create($this->instanceName, $data);
        }

        return null;
    }
}
