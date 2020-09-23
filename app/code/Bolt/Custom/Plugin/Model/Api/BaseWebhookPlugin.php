<?php

namespace Bolt\Custom\Plugin\Model\Api;

class BaseWebhookPlugin
{
    const BOLT_METADATA = 'BOLT_SESSION_METADATA';

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Bolt\Boltpay\Helper\Bugsnag
     */
    protected $bugsnag;

    /**
     * BaseWebhookPlugin constructor.
     * @param \Magento\Framework\Registry  $registry
     * @param \Bolt\Boltpay\Helper\Bugsnag $bugsnag
     */
    public function __construct(\Magento\Framework\Registry $registry, \Bolt\Boltpay\Helper\Bugsnag $bugsnag)
    {
        $this->registry = $registry;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Stores Bolt transaction metadata in memory for later use by
     * @see \Bolt\Custom\Plugin\Helper\SessionPlugin::aroundLoadSession()
     *
     * @param array $metadata to be stored in memory
     */
    protected function storeBoltMetadata($metadata)
    {
        if (key_exists('session', $metadata) && $sessionData = \json_decode($metadata['session'], true)) {
            $this->registry->unregister(self::BOLT_METADATA);
            $this->registry->register(self::BOLT_METADATA, $sessionData);
        }
    }
}
