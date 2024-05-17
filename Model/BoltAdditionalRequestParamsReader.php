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
namespace Bolt\Boltpay\Model;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Serialize\Serializer\Json;
use Bolt\Boltpay\Model\RestApiRequestValidator as BoltRestApiRequestValidator;
use Bolt\Boltpay\Helper\Bugsnag;

/**
 * Read additional params from bolt request
 * In special cases Bolt sends additional data in the request header, this class reads that data
 */
class BoltAdditionalRequestParamsReader
{
    const BOLT_ADDITIONAL_PARAMS_HEADER_KEY = 'X-Bolt-Additional-Params';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var BoltRestApiRequestValidator
     */
    private $boltRestApiRequestValidator;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Request $request
     * @param BoltRestApiRequestValidator $boltRestApiRequestValidator
     * @param Json $json
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Request $request,
        BoltRestApiRequestValidator $boltRestApiRequestValidator,
        Json $json,
        Bugsnag $bugsnag
    ) {
        $this->request = $request;
        $this->boltRestApiRequestValidator = $boltRestApiRequestValidator;
        $this->json = $json;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Returns additional data from bolt request
     *
     * @return array|null
     */
    public function getBoltAdditionalParams(): ?array {
        try {
            // skip if request is not from bolt
            if (!$this->boltRestApiRequestValidator->isValidBoltRequest($this->request)) {
                return null;
            }

            $boltAdditionalParams =  $this->getBoltAdditionalParamsFromRequest();
            if (!is_array($boltAdditionalParams) || empty($boltAdditionalParams)) {
                return null;
            }

            return $boltAdditionalParams;
        } catch (\Exception $e) {
            $this->bugsnag->notifyException($e);
        }

        return null;
    }

    /**
     * Returns bolt additional params from request header
     *
     * @return array|null
     */
    private function getBoltAdditionalParamsFromRequest(): ?array
    {
        $boltAdditionalParamsHeader = $this->request->getHeader(self::BOLT_ADDITIONAL_PARAMS_HEADER_KEY);
        return $boltAdditionalParamsHeader ? $this->json->unserialize($boltAdditionalParamsHeader) : null;
    }
}
