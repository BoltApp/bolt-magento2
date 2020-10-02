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

namespace Bolt\Boltpay\ThirdPartyModules\Aheadworks;

/**
 * Adds compatibility between Boltpay and Aheadworks_Sarp2 modules
 */
class Sarp2
{
    /**
     * @var \Magento\Framework\App\ObjectManager Object Manager instance
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\ObjectManager\ConfigLoaderInterface DI configuration loader
     */
    private $configLoader;

    /**
     * @param \Magento\Framework\ObjectManagerInterface              $objectManager Object Manager instance
     * @param \Magento\Framework\ObjectManager\ConfigLoaderInterface $configLoader DI configuration loader
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\ObjectManager\ConfigLoaderInterface $configLoader
    ) {
        $this->objectManager = $objectManager;
        $this->configLoader = $configLoader;
    }

    /**
     * Aheadworks_Sarp2 adds a check for recurring payments when rendering payment methods,
     * unfortunately it is added in the adminhtml area as opposed to global, where Magento has defined the default ones.
     * This will, instead of adding, completely replace the default checks with Aheadworks’.
     * This is defined in {@see https://devdocs.magento.com/guides/v2.4/extension-dev-guide/build/di-xml-file.html}
     * “When Magento loads a new configuration at a later time, either by a more specific scope or through code,
     * then any array definitions in the new configuration will replace the loaded config instead of merging.”.
     *
     * To resolve this, we merge those configurations in predispatch for the admin order create action.
     */
    public function adminhtmlControllerActionPredispatchSalesOrderCreateIndex()
    {
        $class = 'Magento\Payment\Model\Checks\SpecificationFactory';
        $globalConfig = $this->configLoader->load(\Magento\Framework\App\Area::AREA_GLOBAL);
        $adminConfig = $this->configLoader->load(\Magento\Framework\App\Area::AREA_ADMINHTML);

        if ($this->configLoader instanceof \Magento\Framework\App\ObjectManager\ConfigLoader\Compiled) {
            //PRODUCTION MODE
            $merged['arguments'][$class] = array_merge(
                $globalConfig['arguments'][$class],
                $adminConfig['arguments'][$class]
            );
            $merged['arguments'][$class]['mapping'] = array_merge_recursive(
                $globalConfig['arguments'][$class]['mapping'],
                $adminConfig['arguments'][$class]['mapping']
            );
        } else {
            // DEVELOPER MODE
            $merged = [$class => array_merge_recursive($globalConfig[$class], $adminConfig[$class])];
        }
        $this->objectManager->configure($merged);
    }
}
