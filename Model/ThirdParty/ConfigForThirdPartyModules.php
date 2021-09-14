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

namespace Bolt\Boltpay\Model\ThirdParty;

use Bolt\Boltpay\Model\ThirdParty\DataForThirdPartyModules as Data;

class ConfigForThirdPartyModules implements \Magento\Framework\Event\ConfigInterface
{
    /**
     * Modules configuration model
     *
     * @var Data
     */
    protected $_dataContainer;

    /**
     * ConfigForThirdPartyModules constructor.
     * 
     * @param Data $dataContainer
     */
    public function __construct(Data $dataContainer)
    {
        $this->_dataContainer = $dataContainer;
    }

    /**
     * Get observers by event name
     *
     * @param string $eventName
     * @return null|array|mixed
     */
    public function getObservers($eventName)
    {
        return $this->_dataContainer->get($eventName, []);
    }
}
