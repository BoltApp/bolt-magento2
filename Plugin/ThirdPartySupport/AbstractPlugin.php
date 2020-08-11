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
 * Class AbstractPlugin
 *
 * @property \Bolt\Boltpay\Helper\Log                    logHelper Bolt logging helper
 * @property \Bolt\Boltpay\Helper\Bugsnag                bugsnagHelper Bolt bugsnag helper
 * @property \Magento\Framework\Module\Manager           moduleManager Magento module manager
 * @property \Magento\Framework\Module\ResourceInterface moduleResource Magento module resourc
 * @property string                                      supportedModule Defines the module that needs to be enabled in order for the plugin to execute
 * @property string|null                                 versionFrom If set, defines the minimum version of the supported module that the plugin supports
 * @property string|null                                 versionTo If set, defines the maximum version of the supported module that the plugin supports
 *
 * @package Bolt\Boltpay\Plugin\ThirdPartySupport
 */
abstract class AbstractPlugin
{
    /**
     * @var CommonModuleContext  The common dependency injected interface used by plugins
     */
    private $context;

    /**
     * AbstractPlugin constructor.
     *
     * @param CommonModuleContext $context The common dependcy injected interface used by plugins abstracted away to keep a simple single parameter interface
     */
    public function __construct(CommonModuleContext $context)
    {
        $this->context = $context;
    }

    /**
     * Determines if the plugin should execute its logic if supported module is enabled
     * and version is inside the configured threshold
     *
     * @return bool true if plugin should run, otherwise false
     */
    public function shouldRun()
    {
        $moduleVersion = $this->moduleResource->getDataVersion($this->supportedModule);
        return $this->moduleManager->isEnabled($this->supportedModule)
            && ($this->versionFrom == null || version_compare($this->versionFrom, $moduleVersion, '<='))
            && ($this->versionTo == null || version_compare($this->versionTo, $moduleVersion, '>='));
    }

    /**
     * A convenience method that attempts to retrieve locally inaccessible properties from the local context
     *
     * @param string $propertyName The name of the accessed property
     *
     * @return mixed A value exposed via a context getter method for the property or null if this method does not exists
     */
    public function __get($propertyName)
    {
        $methodName = 'get' . ucfirst($propertyName);

        return method_exists($this->context, $methodName)
            ? $this->context->$methodName()
            : null;
    }
}
