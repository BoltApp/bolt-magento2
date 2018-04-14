<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Helper;

use Bolt\Boltpay\Helper\Logger;
use Bolt\Boltpay\Logger\Logger as BoltLoger;
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
     * @var Logger
     */
    protected $boltLogger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param Context $context
     * @param BoltLoger $boltLogger
     * @param ConfigHelper $configHelper
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        BoltLoger $boltLogger,
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
