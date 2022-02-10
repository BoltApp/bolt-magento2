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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Synolia\Store;

use Bolt\Boltpay\Helper\Hook as HookHelper;
use Magento\Framework\App\State;

/**
 * Plugin for {@see \Synolia\Store\Helper\Data}
 */
class HelperDataPlugin
{
    /** @var State */
    private $appState;
    
    public function __construct(
        State $appState
    ) {
        $this->appState = $appState;
    }
    
    /**
     * Disable clickandcollect for Bolt api request.
     * 
     * @param \Synolia\Store\Helper\Data $subject
     * @param boolean $result
     */
    public function afterGetEnableClickAndCollect(\Synolia\Store\Helper\Data $subject, $result)
    {
        if ($this->appState->getAreaCode() != \Magento\Framework\App\Area::AREA_WEBAPI_REST || !HookHelper::$fromBolt) {
            return $result;
        }

        return false;
    }
}