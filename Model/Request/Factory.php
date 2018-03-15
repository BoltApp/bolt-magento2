<?php
/**
 * Copyright © 2013-2017 Bolt, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bolt\Boltpay\Model\Request;

use Magento\Framework\ObjectManagerInterface;

/**
 * Class Factory
 * Factory class for @see \Bolt\Boltpay\Model\Request
 * @package Bolt\Boltpay\Model\Request
 */
class Factory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected $instanceName;

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        $instanceName = 'Bolt\Boltpay\Model\Request'
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return \Bolt\Boltpay\Model\Request
     */
    public function create(array $data = [])
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
