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
 * @copyright  Copyright (c) 2017-2023 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Bolt\Boltpay\Plugin\Magento\Framework\HTTP\PhpEnvironment;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Bolt\Boltpay\Model\BoltAdditionalRequestParamsReader;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Replacing remote address with data from bolt additional request params
 * It's used in Signifyd_Connect module during the checkout process fraud detection call to Signifyd
 * It's critical to have the correct IP address for fraud detection
 */
class RemoteAddressPlugin
{
    private const BOLT_ADDITIONAL_PARAM_CLIENT_IP_KEY = 'client_ip';

    /**
     * @var BoltAdditionalRequestParamsReader
     */
    private $boltAdditionalRequestParamsReader;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @param BoltAdditionalRequestParamsReader $boltAdditionalRequestParamsReader
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        BoltAdditionalRequestParamsReader $boltAdditionalRequestParamsReader,
        Bugsnag $bugsnag
    ) {
        $this->boltAdditionalRequestParamsReader = $boltAdditionalRequestParamsReader;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Replace remote address with data from bolt additional request params
     *
     * @param RemoteAddress $subject
     * @param $result
     * @return mixed
     */
    public function afterGetRemoteAddress(
        RemoteAddress $subject,
        $result
    ) {
        try {
            $boltAdditionalParams = $this->boltAdditionalRequestParamsReader->getBoltAdditionalParams();
            if (isset($boltAdditionalParams[self::BOLT_ADDITIONAL_PARAM_CLIENT_IP_KEY])) {
                $result = $boltAdditionalParams[self::BOLT_ADDITIONAL_PARAM_CLIENT_IP_KEY];
            }
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }

        return $result;
    }
}
