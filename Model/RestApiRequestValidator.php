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

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as Date;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Helper\Oauth\Data as OauthHelper;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Integration\Api\IntegrationServiceInterface;
use Bolt\Boltpay\Helper\IntegrationManagement as BoltIntegrationManagement;

/**
 * Additional validation for WebApi Rest request from bolt
 */
class RestApiRequestValidator
{
    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Date
     */
    private $date;

    /**
     * @var OauthHelper
     */
    private $oauthHelper;

    /**
     * @param TokenFactory $tokenFactory
     * @param IntegrationServiceInterface $integrationService
     */
    public function __construct(
        TokenFactory $tokenFactory,
        IntegrationServiceInterface $integrationService,
        DateTime $dateTime = null,
        Date $date = null,
        OauthHelper $oauthHelper = null
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->integrationService = $integrationService;
        $this->dateTime = $dateTime;
        $this->date = $date;
        $this->oauthHelper = $oauthHelper;
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
        $token = $this->tokenFactory->create()->loadByToken($bearerToken);

        if (!$token->getId() || $token->getRevoked() || $this->isTokenExpired($token)) {
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

    /**
     * Check if token is expired.
     *
     * @param Token $token
     * @return bool
     */
    private function isTokenExpired(Token $token): bool
    {
        if ($token->getUserType() == UserContextInterface::USER_TYPE_ADMIN) {
            $tokenTtl = $this->oauthHelper->getAdminTokenLifetime();
        } elseif ($token->getUserType() == UserContextInterface::USER_TYPE_CUSTOMER) {
            $tokenTtl = $this->oauthHelper->getCustomerTokenLifetime();
        } else {
            // other user-type tokens are considered always valid
            return false;
        }

        if (empty($tokenTtl)) {
            return false;
        }

        if ($this->dateTime->strToTime($token->getCreatedAt()) < ($this->date->gmtTimestamp() - $tokenTtl * 3600)) {
            return true;
        }

        return false;
    }
}
