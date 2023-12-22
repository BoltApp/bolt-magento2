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
 * @copyright  Copyright (c) 2017-2024 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\WebapiRest\Magento\Framework\Webapi;

use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;
use Bolt\Boltpay\Model\RestApiRequestValidator as BoltRestApiRequestValidator;
use Bolt\Boltpay\Helper\Bugsnag;

class ErrorProcessorPlugin
{
    /**
     * @var Request
     */
    private $restRequest;

    /**
     * @var Bugsnag
     */
    private $bugSnag;

    /**
     * @var BoltRestApiRequestValidator
     */
    private $boltRestApiRequestValidator;

    /**
     * @param Request $restRequest
     * @param BoltRestApiRequestValidator $boltRestApiRequestValidator
     * @param Bugsnag $bugsnag
     */
    public function __construct(
        Request                     $restRequest,
        BoltRestApiRequestValidator $boltRestApiRequestValidator,
        Bugsnag                     $bugsnag
    )
    {
        $this->restRequest = $restRequest;
        $this->boltRestApiRequestValidator = $boltRestApiRequestValidator;
        $this->bugSnag = $bugsnag;
    }

    /**
     * @param \Magento\Framework\Webapi\ErrorProcessor $subject
     * @param $maskedException
     * @param \Exception $exception
     * @return WebapiException|mixed
     */
    public function afterMaskException(
        \Magento\Framework\Webapi\ErrorProcessor $subject,
        $maskedException,
        \Exception                               $exception
    )
    {
        if (
            $this->boltRestApiRequestValidator->isValidBoltRequest($this->restRequest) &&
            $maskedException->getHttpCode() == WebapiException::HTTP_INTERNAL_ERROR
        ) {
            $maskedException = new WebapiException(
                new Phrase($exception->getMessage() . $exception->getTraceAsString()),
                $exception->getCode(),
                WebapiException::HTTP_INTERNAL_ERROR,
                [],
                '',
                null,
                $exception->getTraceAsString()
            );
        }

        return $maskedException;
    }
}
