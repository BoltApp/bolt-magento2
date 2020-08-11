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

namespace Bolt\Boltpay\Plugin\ThirdPartySupport;

/**
 * Context class used to provide Third Party Support plugins with common dependencies
 * like supported module and version thresholds
 */
class CommonModuleContext
{

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag Bolt Bugsnag helper instance
     */
    protected $bugsnagHelper;

    /**
     * @var \Bolt\Boltpay\Helper\Log Bolt logging helper instance
     */
    protected $logHelper;

    /**
     * @var \Magento\Framework\Module\Manager Magento module manager
     */
    protected $moduleManager;

    /**
     * @var \Magento\Framework\Module\ResourceInterface Magento module resource
     */
    protected $moduleResource;

    /**
     * @var string Defines the module that needs to be enabled in order for the plugin to execute
     */
    protected $supportedModule;

    /**
     * @var string|null If set, defines the minimum version of the supported module that the plugin supports
     */
    protected $versionFrom;

    /**
     * @var string|null If set, defines the maximum version of the supported module that the plugin supports
     */
    protected $versionTo;

    /**
     * Context constructor
     *
     * @param \Bolt\Boltpay\Helper\Bugsnag                $bugsnag Bolt Bugsnag helper instance
     * @param \Bolt\Boltpay\Helper\Log                    $logHelper Bolt logging helper instance
     * @param \Magento\Framework\Module\Manager           $moduleManager Magento module manager instance
     * @param \Magento\Framework\Module\ResourceInterface $moduleResource Magento module resource model
     * @param string                                      $supportedModule supported module name
     * @param string|null                                 $versionFrom minimum version of the supported module
     * @param string|null                                 $versionTo maximum version of the supported module
     */
    public function __construct(
        \Bolt\Boltpay\Helper\Bugsnag $bugsnag,
        \Bolt\Boltpay\Helper\Log $logHelper,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        $supportedModule = '',
        $versionFrom = null,
        $versionTo = null
    ) {
        $this->bugsnagHelper = $bugsnag;
        $this->logHelper = $logHelper;
        $this->moduleManager = $moduleManager;
        $this->moduleResource = $moduleResource;
        $this->supportedModule = $supportedModule;
        $this->versionFrom = $versionFrom;
        $this->versionTo = $versionTo;
    }

    /**
     * Get Bolt bugsnag helper
     *
     * @return \Bolt\Boltpay\Helper\Bugsnag
     */
    public function getBugsnagHelper()
    {
        return $this->bugsnagHelper;
    }

    /**
     * Get Bolt log helper
     *
     * @return \Bolt\Boltpay\Helper\Log
     */
    public function getLogHelper()
    {
        return $this->logHelper;
    }

    /**
     * Get Magento Module Manager
     *
     * @return \Magento\Framework\Module\Manager
     */
    public function getModuleManager()
    {
        return $this->moduleManager;
    }

    /**
     * Get Magento Module Resource
     *
     * @return \Magento\Framework\Module\ResourceInterface
     */
    public function getModuleResource()
    {
        return $this->moduleResource;
    }

    /**
     * Get name of the supported module
     *
     * @return string
     */
    public function getSupportedModule()
    {
        return $this->supportedModule;
    }

    /**
     * Get the maximum version of the supported module that the plugin supports
     *
     * @return string|null
     */
    public function getVersionTo()
    {
        return $this->versionTo;
    }

    /**
     * Get the minimum version of the supported module that the plugin supports
     *
     * @return string|null
     */
    public function getVersionFrom()
    {
        return $this->versionFrom;
    }
}
