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
 * @copyright  Copyright (c) 2026 Bolt Financial, Inc (https://www.boltapp.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// phpcs:ignoreFile -- declares several stand-in types, each only if the real one is missing

/**
 * Stand-ins for types the suite mocks but the Magento under test may not have: third-party modules, Adobe
 * Commerce-only modules, and Zend Framework 1 classes Magento 2.4.9 no longer ships.
 *
 * PHPUnit 9 generated an empty class for any unknown type it was asked to mock; PHPUnit 12 refuses. Each
 * stand-in is empty because the tests list the methods to double, and is only declared if the real type
 * cannot be autoloaded.
 */

namespace Aheadworks\RewardPoints\Api {
    if (!interface_exists(CustomerRewardPointsManagementInterface::class)) {
        interface CustomerRewardPointsManagementInterface
        {
        }
    }
}

namespace Aheadworks\RewardPoints\Model {
    if (!class_exists(Config::class)) {
        class Config
        {
        }
    }
}

namespace Aheadworks\RewardPoints\Plugin\Model\Service {
    if (!class_exists(OrderServicePlugin::class)) {
        class OrderServicePlugin
        {
        }
    }
}

namespace Amasty\GiftCardAccount\Api {
    if (!interface_exists(GiftCardAccountRepositoryInterface::class)) {
        interface GiftCardAccountRepositoryInterface
        {
        }
    }

    if (!interface_exists(GiftCardQuoteRepositoryInterface::class)) {
        interface GiftCardQuoteRepositoryInterface
        {
        }
    }
}

namespace Amasty\GiftCardAccount\Model\GiftCardAccount {
    if (!class_exists(Account::class)) {
        class Account
        {
        }
    }

    if (!class_exists(Repository::class)) {
        class Repository
        {
        }
    }
}

namespace Amasty\GiftCardAccount\Model\GiftCardExtension\Order {
    if (!class_exists(Order::class)) {
        class Order
        {
        }
    }

    if (!class_exists(Repository::class)) {
        class Repository
        {
        }
    }
}

namespace Amasty\GiftCard\Model {
    if (!class_exists(Account::class)) {
        class Account
        {
        }
    }
}

namespace ClassyLlama\AvaTax\Plugin\Model\ResourceModel {
    if (!class_exists(ExtensionAttributesPersistencePlugin::class)) {
        class ExtensionAttributesPersistencePlugin
        {
        }
    }
}

namespace MageVision\FreeShippingAdmin\Model\Carrier {
    if (!class_exists(Method::class)) {
        class Method
        {
        }
    }
}

namespace Magento\CustomerBalance\Model {
    if (!class_exists(Balance::class)) {
        class Balance
        {
        }
    }
}

namespace Magento\GiftCardAccount\Model {
    if (!class_exists(Giftcardaccount::class)) {
        class Giftcardaccount
        {
        }
    }
}

namespace Magento\GiftWrapping\Model {
    if (!class_exists(Wrapping::class)) {
        class Wrapping
        {
        }
    }
}

namespace Magento\GiftWrapping\Model\Total\Quote {
    if (!class_exists(Giftwrapping::class)) {
        class Giftwrapping
        {
        }
    }
}

namespace Magento\Reward\Controller\Cart {
    if (!class_exists(Remove::class)) {
        class Remove
        {
        }
    }
}

namespace Magento\Reward\Model {
    if (!class_exists(Reward::class)) {
        class Reward
        {
        }
    }
}

namespace Mirasvit\Rewards\Model {
    if (!class_exists(Purchase::class)) {
        class Purchase
        {
        }
    }
}

namespace Webkul\Odoomagentoconnect\Model\ResourceModel {
    if (!class_exists(Order::class)) {
        class Order
        {
        }
    }
}

namespace Webkul\Odoomagentoconnect\Observer {
    if (!class_exists(SalesOrderAfterObserver::class)) {
        class SalesOrderAfterObserver
        {
        }
    }
}

namespace {
    if (!class_exists(Zend_Http_Response::class)) {
        class Zend_Http_Response
        {
        }
    }

    if (!class_exists(Zend_Validate_Exception::class)) {
        class Zend_Validate_Exception extends \Exception
        {
        }
    }
}
