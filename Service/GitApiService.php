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

declare(strict_types=1);

namespace Bolt\Boltpay\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class GitApiService
 */
class GitApiService
{
    const API_REQUEST_URI = 'https://api.github.com/';

    const API_LATEST_RELEASE = 'repos/BoltApp/bolt-magento2/releases/latest';

    const API_SUCCESS_RESPONSE_CODE = 200;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param ClientFactory $clientFactory
     * @param ResponseFactory $responseFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory,
        SerializerInterface $serializer
    ) {
        $this->clientFactory = $clientFactory;
        $this->responseFactory = $responseFactory;
        $this->serializer = $serializer;
    }

    public function getLatestRelease(): array
    {
        $response = $this->doRequest(self::API_LATEST_RELEASE);

        $status = $response->getStatusCode();
        if($status !== self::API_SUCCESS_RESPONSE_CODE) {
            return ['status' => $status, 'response_body' => $response->getReasonPhrase()];
        }

        $responseBody = $response->getBody();
        $responseContent = $responseBody->getContents();

        return ['status' => $status, 'response_body' => $this->serializer->unserialize($responseContent)];
    }

    /**
     * @param string $uriEndpoint
     * @param array $params
     * @param string $requestMethod
     *
     * @return Response
     */
    private function doRequest(
        string $uriEndpoint,
        array $params = [],
        string $requestMethod = Request::HTTP_METHOD_GET
    ): Response {
        /** @var Client $client */
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => self::API_REQUEST_URI
        ]]);

        try {
            $response = $client->request(
                $requestMethod,
                $uriEndpoint,
                $params
            );
        } catch (GuzzleException $exception) {
            /** @var Response $response */
            $response = $this->responseFactory->create([
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ]);
        }

        return $response;
    }
}
