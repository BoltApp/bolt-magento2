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

use Bolt\Boltpay\Model\ThirdParty\ReaderForThirdPartyModules as Reader;

class DataForThirdPartyModules extends \Magento\Framework\Config\Data\Scoped
{
    /**
     * Scope priority loading scheme
     *
     * @var array
     */
    protected $_scopePriorityScheme = ['global'];

    /**
     * Constructor
     *
     * @param \Magento\Framework\Event\Config\Reader $reader
     * @param \Magento\Framework\Config\ScopeInterface $configScope
     * @param \Magento\Framework\Config\CacheInterface $cache
     * @param string|null $cacheId
     * @param SerializerInterface|null $serializer
     */
    public function __construct(
        Reader $reader,
        \Magento\Framework\Config\ScopeInterface $configScope,
        \Magento\Framework\Config\CacheInterface $cache,
        $cacheId = 'bolt_event_config_cache',
        \Magento\Framework\Serialize\SerializerInterface $serializer = null
    ) {
        parent::__construct($reader, $configScope, $cache, $cacheId, $serializer);
    }
}
