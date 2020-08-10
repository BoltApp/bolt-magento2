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

namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Logger\Logger as BoltLogger;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Boltpay Log helper
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Log extends AbstractHelper
{
    /**
     * @var BoltLogger
     */
    private $boltLogger;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param Context $context
     * @param BoltLogger $boltLogger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context $context,
        BoltLogger $boltLogger,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->boltLogger = $boltLogger;
        $this->configHelper = $configHelper;
    }

    /**
     * Add info log
     *
     * @param mixed $info log message
     *
     * @return Log
     */
    public function addInfoLog($info)
    {
        if ($this->configHelper->isDebugModeOn()) {
            $this->boltLogger->info($info);
        }
        return $this;
    }
}
