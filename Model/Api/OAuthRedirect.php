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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\OAuthRedirectInterface;
use Bolt\Boltpay\Helper\FeatureSwitch\Decider as DeciderHelper;
use Magento\Framework\Webapi\Rest\Response;

class OAuthRedirect implements OAuthRedirectInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var DeciderHelper
     */
    private $deciderHelper;

    /**
     * @param Response      $response
     * @param DeciderHelper $deciderHelper
     */
    public function __construct(
        Response $response,
        DeciderHelper $deciderHelper
    ) {
        $this->response = $response;
        $this->deciderHelper = $deciderHelper;
    }

    /**
     * Login with Bolt SSO and redirect
     *
     * @api
     *
     * @param string $authorization_code
     *
     * @return void
     */
    public function login($authorization_code = '')
    {
        if (!$this->deciderHelper->isBoltSSOEnabled()) {
            $this->sendErrorResponse('Bolt SSO feature not enabled');
        }

        $this->sendErrorResponse('Not implemented');
    }

    private function sendErrorResponse($message)
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHttpResponseCode(500);
        $this->response->setBody(json_encode(['message' => $message]));
        $this->response->sendResponse();
    }
}
