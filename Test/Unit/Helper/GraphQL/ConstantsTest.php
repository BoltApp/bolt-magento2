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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper\GraphQL;

use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\GraphQL\Constants;

/**
 * Class ConstantsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper\GraphQL
 */
class ConstantsTest extends TestCase
{
    /**
     * @test
     */
    public function getPluginTypeConstant()
    {
        $this->assertEquals('MAGENTO_2', Constants::PLUGIN_TYPE);
    }

    /**
     * @test
     */
    public function GetFeatureSwitchesQueryConstant()
    {
        $this->assertEquals(
            'query GetFeatureSwitches($type: PluginType!, $version: String!) {
  plugin(type: $type, version: $version) {
    features {
      name
      value
      defaultValue
      rolloutPercentage
    }
  }
}', Constants::GET_FEATURE_SWITCHES_QUERY);
    }

    /**
     * @test
     */
    public function getSendLogsQueryConstant()
    {
        $this->assertEquals('mutation LogMerchantLogs($logs: [LogLine!]!) {
  logMerchantLogs(logs: $logs){
    isSuccessful
  }
}', Constants::SEND_LOGS_QUERY);
    }

    /**
     * @test
     */
    public function getFeatureSwitchesOperationConstant()
    {
        $this->assertEquals('GetFeatureSwitches', Constants::GET_FEATURE_SWITCHES_OPERATION);
    }

    /**
     * @test
     */
    public function getSendLogsOperationConstant()
    {
        $this->assertEquals('LogMerchantLogs', Constants::SEND_LOGS_OPERATION);
    }
}

