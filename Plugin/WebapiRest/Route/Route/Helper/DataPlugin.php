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
 * @copyright  Copyright (c) 2017-2022 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\WebapiRest\Route\Route\Helper;

/**
 * Plugin for {@see \Route\Route\Helper\Data}
 */
class DataPlugin
{
    public const IS_INSURED_PARAM = 'routeIsInsured';
    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $request;

    public function __construct(\Magento\Framework\Webapi\Rest\Request $request)
    {
        $this->request = $request;
    }

    public function afterIsInsured(\Route\Route\Helper\Data $subject, $result): bool
    {
        $requestParams = $this->request->getParams();
        if (array_key_exists(self::IS_INSURED_PARAM, $requestParams)) {
            return filter_var($requestParams[self::IS_INSURED_PARAM], FILTER_VALIDATE_BOOLEAN);
        }
        return $result;
    }
}
