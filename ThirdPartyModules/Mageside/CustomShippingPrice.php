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

namespace Bolt\Boltpay\ThirdPartyModules\Mageside;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Session as BoltSession;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\User\Model\UserFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface as Serialize;

class CustomShippingPrice
{
    /**
     * @var Bugsnag
     */
    private $bugsnagHelper;

    /**
     * @var AuthSession
     */
    private $authSession;
    
    /**
     * @var UserFactory
     */
    private $userFactory;
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var Serialize
     */
    private $serialize;

    /**
     * CustomShippingPrice constructor.
     *
     * @param Bugsnag $bugsnagHelper
     * @param AuthSession $authSession
     * @param UserFactory $userFactory
     * @param CacheInterface $cache
     * @param Serialize $serialize
     */
    public function __construct(
        Bugsnag $bugsnagHelper,
        AuthSession $authSession,
        UserFactory $userFactory,
        CacheInterface $cache,
        Serialize $serialize
    ) {
        $this->bugsnagHelper = $bugsnagHelper;
        $this->authSession = $authSession;
        $this->userFactory = $userFactory;
        $this->cache = $cache;
        $this->serialize = $serialize;
    }

    /**
     * Add the admin id to the session data, then we can restore the auth session when needed.
     *
     * @param array $sessionData
     * @param int|string $quoteId
     * @param mixed $checkoutSession
     */
    public function saveSessionData($sessionData, $quoteId, $checkoutSession)
    {
        try {
            if ($sessionData['sessionType'] == 'admin' && $this->authSession->isLoggedIn()) {
                $sessionData["adminUserId"] = $this->authSession->getUser()->getId();
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
        
        return $sessionData;
    }
    
    /**
     * Mageside custom shipping is only allowed to be used from the admin system,
     * so we need to restore the auth session.
     *
     * @param Quote $quote
     */
    public function afterLoadSession($quote)
    {
        try {
            $cacheIdentifier = BoltSession::BOLT_SESSION_PREFIX . $quote->getBoltParentQuoteId();
            if ($serialized = $this->cache->load($cacheIdentifier)) {
                $sessionData = $this->serialize->unserialize($serialized);
                if ($sessionData['sessionType'] == 'admin' &&
                    isset($sessionData["adminUserId"]) &&
                    !$this->authSession->isLoggedIn()
                ) {
                    $user = $this->userFactory->create()->load($sessionData["adminUserId"]);
                    $this->authSession->setUser($user);
                    $this->authSession->processLogin();
                }
            }
        } catch (\Exception $e) {
            $this->bugsnagHelper->notifyException($e);
        }
    }
}
