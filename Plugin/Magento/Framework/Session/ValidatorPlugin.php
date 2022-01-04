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

namespace Bolt\Boltpay\Plugin\Magento\Framework\Session;

use Bolt\Boltpay\Helper\Hook;
use Magento\Framework\Session\Validator;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class ValidatorPlugin
 * @package Bolt\Boltpay\Plugin\Magento\Framework\Session
 */
class ValidatorPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * ValidatorPlugin constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param Validator $subject
     * @param callable $proceed
     * @param SessionManagerInterface $session
     */
    public function aroundValidate(
        Validator $subject,
        callable $proceed,
        SessionManagerInterface $session
    )
    {
        if ($this->scopeConfig->getValue(Validator::XML_PATH_USE_REMOTE_ADDR) && Hook::$fromBolt) {
            return;
        }

        $proceed($session);
    }
}
