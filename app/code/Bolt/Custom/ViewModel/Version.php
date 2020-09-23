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

namespace Bolt\Custom\ViewModel;

class Version implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var \Magento\Framework\Module\ResourceInterface Magento module model resource
     */
    private $moduleResource;

    /**
     * Version constructor.
     * @param \Magento\Framework\Module\ResourceInterface $moduleResource Magento module model resource
     */
    public function __construct(\Magento\Framework\Module\ResourceInterface $moduleResource)
    {
        $this->moduleResource = $moduleResource;
    }

    /**
     * Gets Bolt M2 customization module version
     *
     * @return false|string current module version or false if not installed
     */
    public function getVersion()
    {
        return $this->moduleResource->getDataVersion('Bolt_Custom');
    }
}