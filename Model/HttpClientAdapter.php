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
namespace Bolt\Boltpay\Model;

use Bolt\Boltpay\Helper\Config as BoltConfigHelper;
use Laminas\Http\Headers;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\ZendClient;

/**
 * Bolt http client adapter
 * In magento 2.26.0 and above the "Magento\Framework\HTTP\ZendClient" class is deprecated and can't be used.
 * Instead, we should use "Magento\Framework\HTTP\LaminasClient" class
 * This class is created to avoid 2.26.0 client adapter issues related to deprecated classes.
 */
class HttpClientAdapter
{
    /**
     * @var LaminasClient|ZendClient
     */
    private $client;

    /**
     * @var BoltConfigHelper
     */
    private $boltConfigHelper;

    /**
     * @param BoltConfigHelper $boltConfigHelper
     */
    public function __construct(BoltConfigHelper $boltConfigHelper)
    {
        $this->boltConfigHelper = $boltConfigHelper;
        $this->client = version_compare($this->boltConfigHelper->getStoreVersion(), '2.4.6', '>=')
            ? ObjectManager::getInstance()->create(LaminasClient::class)
            : ObjectManager::getInstance()->create(ZendClient::class);
    }

    /**
     * Set the uri path
     *
     * @param string $uri
     * @return $this
     */
    public function setUri($uri)
    {
        $this->client->setUri($uri);
        return $this;
    }

    /**
     * Set client config options
     *
     * @param array $config
     * @return $this
     */
    public function setConfig($config = array())
    {
        if ($this->client instanceof LaminasClient) {
            $this->client->setOptions($config);
        } else {
            $this->client->setConfig($config);
        }
        return $this;
    }

    /**
     * Set client headers
     *
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        if ($this->client instanceof LaminasClient) {
            $headersObject = new Headers();
            foreach ($headers as $headerName => $headerValue) {
                $headersObject->addHeaderLine($headerName, $headerName . ':' . $headerValue);
            }
            $this->client->setHeaders($headersObject);
        } else {
            $this->client->setHeaders($headers);
        }
        return $this;
    }

    /**
     * Set client raw body data
     *
     * @param string $rawData
     * @param string $enctype
     * @return $this
     */
    public function setRawData($rawData, $enctype = null)
    {
        if ($this->client instanceof LaminasClient) {
            $this->client->setEncType($enctype);
            $this->client->setRawBody($rawData);
        } else {
            $this->client->setRawData($rawData, $enctype);
        }
        return $this;
    }

    /**
     * Set post parameters
     *
     * @param array $post
     * @return $this
     */
    public function setParameterPost($post)
    {
        $this->client->setParameterPost($post);
        return $this;
    }

    /**
     * Send client request
     *
     * @param string $methodType
     * @return \Laminas\Http\Response|void
     */
    public function request($methodType)
    {
        if ($this->client instanceof LaminasClient) {
            $this->client->setMethod($methodType);
            return $this->client->send();
        } else {
            return $this->client->request($methodType);
        }
    }
}
