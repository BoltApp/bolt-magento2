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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Block;

/**
 * MinifiedJs Block. The block class used in replace.phtml and boltglobaljs blocks.
 */
class MinifiedJs extends Js
{
    /**
     * Blocks name in layouts list which available for rendering whether the feature switch is on or not
     *
     * @var string[]
     */
    private $allowedBlocksToRender = ['boltglobaljs'];

    /**
     * @inheriDoc
     */
    protected function _toHtml()
    {
        if (!$this->featureSwitches->isEnabledFetchCartViaApi() ||
            in_array($this->getNameInLayout(), $this->allowedBlocksToRender)
        ) {
            return $this->minifyJs(parent::_toHtml());
        }
        return '';
    }
}
