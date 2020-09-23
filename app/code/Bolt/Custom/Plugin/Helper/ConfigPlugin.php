<?php

namespace Bolt\Custom\Plugin\Helper;

/**
 * Plugin for {@see \Bolt\Boltpay\Helper\Config}
 */
class ConfigPlugin
{
    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    private $bugsnag;

    /**
     * @var \Bolt\Boltpay\Helper\Log
     */
    private $logHelper;

    /**
     * ConfigPlugin constructor.
     * @param \Bolt\Boltpay\Helper\Bugsnag $bugsnag
     * @param \Bolt\Boltpay\Helper\Log     $logHelper
     */
    public function __construct(\Bolt\Boltpay\Helper\Bugsnag $bugsnag, \Bolt\Boltpay\Helper\Log $logHelper)
    {
        $this->bugsnag = $bugsnag;
        $this->logHelper = $logHelper;
    }
}
