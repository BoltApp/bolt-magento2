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
use Magento\Integration\Api\UserTokenReaderInterface;
use Magento\Integration\Api\UserTokenValidatorInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Bolt\Boltpay\Helper\IntegrationManagement as BoltIntegrationManagement;

/**
 * Additional validation for WebApi Rest request from bolt
 */
class RestApiRequestValidator
{
    /**
     * @var UserTokenReaderInterface
     */
    private $userTokenReader;

    /**
     * @var UserTokenValidatorInterface
     */
    private $userTokenValidator;

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;

    /**
     * @param UserTokenReaderInterface $userTokenReader
     * @param UserTokenValidatorInterface $userTokenValidator
     * @param IntegrationServiceInterface $integrationService
     */
    public function __construct(
        UserTokenReaderInterface $userTokenReader,
        UserTokenValidatorInterface $userTokenValidator,
        IntegrationServiceInterface $integrationService
    ) {
        $this->userTokenReader = $userTokenReader;
        $this->userTokenValidator = $userTokenValidator;
        $this->integrationService = $integrationService;
    }

    /**
     * Identify/Validate WebAPI request from bolt
     * returns false if request was initiated from non-bolt integration
     * or if request is not valid (expired keys etc.)
     *
     * @param Request $restRequest
     * @return bool
     */
    public function isValidBoltRequest(Request $restRequest): bool
    {
        $authorizationHeaderValue = $restRequest->getHeader('Authorization');
        if (!$authorizationHeaderValue) {
            return false;
        }

        $headerPieces = explode(" ", $authorizationHeaderValue);
        if (count($headerPieces) !== 2) {
            return false;
        }

        $tokenType = strtolower($headerPieces[0]);
        if ($tokenType !== 'bearer') {
            return false;
        }

        $bearerToken = $headerPieces[1];
        try {
            $token = $this->userTokenReader->read($bearerToken);
        } catch (\Exception $e) {
            return false;
        }

        try {
            $this->userTokenValidator->validate($token);
        } catch (\Exception $e) {
            return false;
        }

        $userContext = $token->getUserContext();
        if (!$userContext->getUserId()) {
            return false;
        }

        try {
            $integration = $this->integrationService->findByConsumerId($userContext->getUserId());
        } catch (\Exception $e) {
            return false;
        }

        // checking integration name it should be boltIntegration
        if ($integration->getName() !== BoltIntegrationManagement::BOLT_INTEGRATION_NAME) {
            return false;
        }

        return true;
    }
}
