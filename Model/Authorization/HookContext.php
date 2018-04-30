<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bolt\Boltpay\Model\Authorization;

use Magento\Authorization\Model\UserContextInterface;

/**
 * Session-based customer user context
 */
class HookContext implements UserContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function getUserId()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserType()
    {
        return UserContextInterface::USER_TYPE_INTEGRATION;
    }
}
