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

use Bolt\Boltpay\ThirdPartyModules\Aheadworks\Giftcard as Aheadworks_Giftcard;
use Bolt\Boltpay\ThirdPartyModules\Aheadworks\RewardPoints as Aheadworks_RewardPoints;
use Bolt\Boltpay\ThirdPartyModules\Mageplaza\ShippingRestriction as Mageplaza_ShippingRestriction;
use Bolt\Boltpay\ThirdPartyModules\Mirasvit\Credit as Mirasvit_Credit;
use Bolt\Boltpay\ThirdPartyModules\IDme\GroupVerification as IDme_GroupVerification;
use Bolt\Boltpay\ThirdPartyModules\Amasty\Rewards as Amasty_Rewards;
use Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCardAccount as Amasty_GiftCardAccount;
use Bolt\Boltpay\ThirdPartyModules\Amasty\GiftCard as Amasty_GiftCard;
use Bolt\Boltpay\ThirdPartyModules\Amasty\Extrafee as Amasty_Extrafee;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\Donations as MageWorx_Donations;
use Bolt\Boltpay\ThirdPartyModules\MageWorld\RewardPoints as MW_RewardPoints;
use Bolt\Boltpay\ThirdPartyModules\Bss\StoreCredit as Bss_StoreCredit;
use Bolt\Boltpay\ThirdPartyModules\Listrak\Remarketing as Listrak_Remarketing;
use Bolt\Boltpay\ThirdPartyModules\Aheadworks\StoreCredit as Aheadworks_StoreCredit;
use Bolt\Boltpay\ThirdPartyModules\Mageplaza\GiftCard as Mageplaza_GiftCard;
use Bolt\Boltpay\ThirdPartyModules\Mirasvit\Rewards as Mirasvit_Rewards;
use Bolt\Boltpay\ThirdPartyModules\Amasty\StoreCredit as Amasty_StoreCredit;
use Bolt\Boltpay\ThirdPartyModules\Magento\GiftCardAccount as Magento_GiftCardAccount;
use Bolt\Boltpay\ThirdPartyModules\Magento\SalesRuleStaging as Magento_SalesRuleStaging;
use Bolt\Boltpay\ThirdPartyModules\Zonos\DutyTax as Zonos_DutyTax;
use Bolt\Boltpay\ThirdPartyModules\Mageside\CustomShippingPrice as Mageside_CustomShippingPrice;
use Bolt\Boltpay\ThirdPartyModules\MageWorld\Affiliate as MW_Affiliate;
use Bolt\Boltpay\ThirdPartyModules\Magento\InStorePickupShipping as Magento_InStorePickupShipping;
use Bolt\Boltpay\ThirdPartyModules\Magecomp\Extrafee as Magecomp_Extrafee;
use Bolt\Boltpay\ThirdPartyModules\Webkul\Odoomagentoconnect as Webkul_Odoomagentoconnect;
use Bolt\Boltpay\ThirdPartyModules\J2t\Rewardpoints as J2t_Rewardpoints;
use Bolt\Boltpay\ThirdPartyModules\BagRiders\StoreCredit as BagRiders_StoreCredit;
use Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance as Magento_CustomerBalance;
use Bolt\Boltpay\ThirdPartyModules\Teamwork\Token as Teamwork_Token;
use Bolt\Boltpay\ThirdPartyModules\Teamwork\StoreCredit as Teamwork_StoreCredit;
use Bolt\Boltpay\ThirdPartyModules\SomethingDigital\InStorePickupBoltIntegration as SomethingDigital_InStorePickupBoltIntegration;
use Bolt\Boltpay\ThirdPartyModules\Route\Route as Route_Route;
use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\ThirdPartyModules\ImaginationMedia\TmwGiftCard as ImaginationMedia_TmwGiftCard;
use Bolt\Boltpay\ThirdPartyModules\Amasty\Preorder as Amasty_Preorder;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\RewardPoints as MageWorx_RewardPoints;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\ShippingRules as MageWorx_ShippingRules;
use Bolt\Boltpay\ThirdPartyModules\MageWorx\Pickup as MageWorx_Pickup;
use Bolt\Boltpay\ThirdPartyModules\Amasty\Promo as Amasty_Promo;
use Bolt\Boltpay\ThirdPartyModules\Mexbs\Tieredcoupon as Mexbs_Tieredcoupon;
use Bolt\Boltpay\ThirdPartyModules\Magento\CompanyPayment as Magento_CompanyPayment;
use Bolt\Boltpay\ThirdPartyModules\Amasty\Affiliate as Amasty_Affiliate;
use Bolt\Boltpay\ThirdPartyModules\Magento\GiftCard as Magento_GiftCard;
use Exception;

class EventsForThirdPartyModules
{
    const eventListeners = [
        'adminhtmlControllerActionPredispatchSalesOrderCreateIndex' => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Sarp2",
                    "boltClass" => \Bolt\Boltpay\ThirdPartyModules\Aheadworks\Sarp2::class,
                ],
            ]
        ],
        "afterLoadSession" => [
            "listeners" => [
                [
                    "module" => "Mageplaza_ShippingRestriction",
                    "checkClasses" => ["Mageplaza\ShippingRestriction\Helper\Data"],
                    "boltClass" => Mageplaza_ShippingRestriction::class,
                ],
                [
                    "module" => "IDme_GroupVerification",
                    "boltClass" => IDme_GroupVerification::class,
                ],
                [
                    "module" => "Mageside_CustomShippingPrice",
                    "checkClasses" => ["Mageside\CustomShippingPrice\Model\Carrier\CustomShipping"],
                    "boltClass" => Mageside_CustomShippingPrice::class,
                ],
                [
                    "module" => "MW_Affiliate",
                    "checkClasses" => ["MW\Affiliate\Helper\Data"],
                    "boltClass" => MW_Affiliate::class,
                ],
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                [
                    "module" => "Amasty_Affiliate",
                    "boltClass" => Amasty_Affiliate::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        "beforeFailedPaymentOrderSave" => [
            "listeners" => [
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Model\GiftCardAccount\Repository',
                        'Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository',
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"      => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses" => [
                        'Amasty\GiftCard\Model\ResourceModel\Quote\CollectionFactory',
                        'Amasty\GiftCard\Api\CodeRepositoryInterface',
                        'Amasty\GiftCard\Api\AccountRepositoryInterface'
                    ],
                    "boltClass"   => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["\Aheadworks\Giftcard\Plugin\Model\Service\OrderServicePlugin"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                [
                    "module" => "Aheadworks_RewardPoints",
                    "sendClasses" => ["Aheadworks\RewardPoints\Plugin\Model\Service\OrderServicePlugin"],
                    "boltClass" => Aheadworks_RewardPoints::class,
                ],
            ],
        ],
        'beforeApplyDiscount' => [
            "listeners" => [
                [
                    "module" => "IDme_GroupVerification",
                    "boltClass" => IDme_GroupVerification::class,
                ],
            ]
        ],
        'beforePrepareQuote' => [
            "listeners" => [
                [
                    "module" => "MW_RewardPoints",
                    "sendClasses" => ["MW\RewardPoints\Helper\Data",
                                      "MW\RewardPoints\Model\CustomerFactory"],
                    "boltClass" => MW_RewardPoints::class,
                ],
                [
                    "module" => "J2t_Rewardpoints",
                    "sendClasses" => [
                        "J2t\Rewardpoints\Helper\Data",
                    ],
                    "boltClass" => J2t_Rewardpoints::class,
                ],
            ]
        ],
        'replicateQuoteData' => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Model\Service\GiftcardCartService",
                                      "Aheadworks\Giftcard\Model\ResourceModel\Giftcard\Quote\CollectionFactory"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"      => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "boltClass"   => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "sendClasses" => ["Amasty\Rewards\Model\ResourceModel\Quote",
                                      "Amasty\Rewards\Model\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'sendClasses' => [
                        'Amasty\Extrafee\Model\ResourceModel\ExtrafeeQuote\CollectionFactory',
                        'Amasty\Extrafee\Model\TotalsInformationManagement'
                    ],
                    'boltClass'   => Amasty_Extrafee::class,
                ]
            ]
        ],
        'applyExternalDiscountData' => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Balance",
                                      "Mirasvit\Rewards\Helper\Balance\SpendRulesList",
                                      "Mirasvit\Rewards\Model\Config",
                                      "Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "sendClasses" => ["Amasty\Rewards\Model\ResourceModel\Quote",
                                      "Amasty\Rewards\Model\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
            ]
        ],
        'removeAppliedStoreCredit' => [
            "listeners" => [
                [
                    "module" => "Amasty_StoreCredit",
                    "checkClasses" => ["Amasty\StoreCredit\Api\Data\SalesFieldInterface"],
                    "sendClasses" => ["Amasty\StoreCredit\Model\StoreCredit\ApplyStoreCreditToQuote"],
                    "boltClass" => Amasty_StoreCredit::class,
                ],
                [
                    "module" => "Aheadworks_StoreCredit",
                    "checkClasses" => ["Aheadworks\StoreCredit\Model\TransactionRepository"],
                    "boltClass" => Aheadworks_StoreCredit::class,
                ],
                [
                    "module" => "Aheadworks_RewardPoints",
                    "boltClass" => Aheadworks_RewardPoints::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "sendClasses" => ["Amasty\Rewards\Api\CheckoutRewardsManagementInterface",
                                      "Amasty\Rewards\Model\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
                [
                    "module" => "MW_RewardPoints",
                    "checkClasses" => ["MW\RewardPoints\Helper\Data"],
                    "boltClass" => MW_RewardPoints::class,
                ],
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Checkout"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => ["Mirasvit\Credit\Helper\Data",
                                      "Mirasvit\Credit\Service\Calculation"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
                [
                    "module" => "J2t_Rewardpoints",
                    "checkClasses" => [
                        "J2t\Rewardpoints\Helper\Data",
                    ],
                    "boltClass" => J2t_Rewardpoints::class,
                ],
                [
                    "module" => "BagRiders_StoreCredit",
                    "checkClasses" => ["BagRiders\StoreCredit\Api\Data\SalesFieldInterface"],
                    "sendClasses" => ["BagRiders\StoreCredit\Model\StoreCredit\ApplyStoreCreditToQuote"],
                    "boltClass" => BagRiders_StoreCredit::class,
                ],
                [
                    "module" => "Magento_CustomerBalance",
                    "checkClasses" => ["Magento\CustomerBalance\Model\Balance"],
                    "boltClass" => Magento_CustomerBalance::class,
                ],
            ]
        ],
        'beforeValidateQuoteDataForProcessNewOrder' => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Balance",
                                      "Mirasvit\Rewards\Helper\Balance\SpendRulesList",
                                      "Mirasvit\Rewards\Model\Config",
                                      "Mirasvit\Rewards\Helper\Checkout",
                                      "Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                [
                    "module" => "BagRiders_StoreCredit",
                    "checkClasses" => ["BagRiders\StoreCredit\Api\Data\SalesFieldInterface"],
                    "sendClasses" => ["BagRiders\StoreCredit\Api\StoreCreditRepositoryInterface"],
                    "boltClass" => BagRiders_StoreCredit::class,
                ],
            ]
        ],
        'clearExternalData' => [
            "listeners" => [
                'Amasty Giftcard V2' => [
                    "module"    => "Amasty_GiftCardAccount",
                    "boltClass" => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"       => "Amasty_GiftCard",
                    "checkClasses" => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "boltClass"    => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "checkClasses" => ["Amasty\Rewards\Model\ResourceModel\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
            ]
        ],
        'deleteRedundantDiscounts' => [
            "listeners" => [
                'Amasty Giftcard V2' => [
                    "module"    => "Amasty_GiftCardAccount",
                    "boltClass" => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"       => "Amasty_GiftCard",
                    "checkClasses" => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "boltClass"    => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "checkClasses" => ["Amasty\Rewards\Model\ResourceModel\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
            ]
        ],
        'removeAmastyGiftCard' => [
            "listeners" => [
                'Amasty Giftcard V2' => [
                    "module"    => "Amasty_GiftCardAccount",
                    "sendClasses" => ["Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountManagement"],
                    "boltClass" => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"       => "Amasty_GiftCard",
                    "checkClasses" => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "boltClass"    => Amasty_GiftCard::class,
                ],
            ]
        ],
        "restoreSessionData" => [
            "listeners" => [
                [
                    "module"      => "IDme_GroupVerification",
                    "boltClass"   => IDme_GroupVerification::class,
                ],
            ],
        ],
        "beforeOrderDeleteRedundantQuotes" => [
            "listeners" => [
                [
                    "module" => "Zonos_DutyTax",
                    "checkClasses" => ["Zonos\DutyTax\Model\ResourceModel\ZonosShippingQuotesResourceModel"],
                    "boltClass" => Zonos_DutyTax::class,
                ],
            ]
        ],
        "beforeCartDeleteQuote" => [
            "listeners" => [
                [
                    "module" => "Zonos_DutyTax",
                    "checkClasses" => ["Zonos\DutyTax\Model\ResourceModel\ZonosShippingQuotesResourceModel"],
                    "boltClass" => Zonos_DutyTax::class,
                ],
            ]
        ],
        "setExtraAddressInformation" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupQuote\Model\Address\SetAddressPickupLocation"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "setInStoreShippingMethodForPrepareQuote" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupQuote\Model\Address\SetAddressPickupLocation"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                'SomethingDigital_InStorePickupBoltIntegration' => [
                    "module" => "SomethingDigital_InStorePickupBoltIntegration",
                    "sendClasses" => ["SomethingDigital\InStorePickupBoltIntegration\Helper\PickupStoreChecker"],
                    "checkClasses" => ["Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver"],
                    "boltClass" => SomethingDigital_InStorePickupBoltIntegration::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "setInStoreShippingAddressForPrepareQuote" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupQuote\Model\ToQuoteAddress",
                                      "Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup",
                                       "Magento\InventorySalesApi\Api\Data\SalesChannelInterface"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                'SomethingDigital_InStorePickupBoltIntegration' => [
                    "module" => "SomethingDigital_InStorePickupBoltIntegration",
                    "sendClasses" => [
                        "SomethingDigital\InStorePickupBoltIntegration\Helper\PickupStoreChecker",
                        "Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver"
                    ],
                    "checkClasses" => [
                        "Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver",
                    ],
                    "boltClass" => SomethingDigital_InStorePickupBoltIntegration::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "sendClasses" => [
                        "MageWorx\Locations\Api\LocationRepositoryInterface"
                    ],
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        'afterUpdateOrderPayment' => [
            "listeners" => [
                'Webkul_Odoomagentoconnect' => [
                    "module" => "Webkul_Odoomagentoconnect",
                    'sendClasses' => ['Webkul\Odoomagentoconnect\Observer\SalesOrderAfterObserver'],
                    "boltClass" => Webkul_Odoomagentoconnect::class,
                ],
            ],
        ],
        'beforeGetCartDataForCreateCart' => [
            "listeners" => [
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'sendClasses' => ['Route\Route\Model\Route\Merchant',
                        'Route\Route\Helper\Data'],
                    'boltClass'   => Route_Route::class,
                ],
            ],
        ],
        'beforeGetBoltpayOrderForBackofficeOrder' => [
            "listeners" => [
                'MageWorx_ShippingRules' => [
                    "module" => "MageWorx_ShippingRules",
                    'checkClasses' => ['MageWorx\ShippingRules\Model\Plugin\Shipping\Rate\Result\Append'],
                    "boltClass" => MageWorx_ShippingRules::class,
                ],
            ],
        ],
        'orderPostprocess' => [
            "listeners" => [
                'Amasty Giftcard V2.5' => [
                    "module"       => "Amasty_GiftCardAccount",
                    "checkClasses" => self::AMASTY_GIFTCARD_V25_CHECK_CLASSES,
                    'sendClasses'  => ['Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountTransactionProcessor',
                                       'Amasty\GiftCardAccount\Model\GiftCardAccount\Repository'],
                    "boltClass"    => Amasty_GiftCardAccount::class,
                ],
            ],
        ],
        'shouldDisableBoltCheckout' => [
            "listeners" => [
                'Magento_CompanyPayment' => [
                    'module'      => 'Magento_CompanyPayment',
                    'checkClasses' => ['Magento\CompanyPayment\Model\Payment\Checks\CanUseForCompany'],
                    'boltClass'   => Magento_CompanyPayment::class,
                ],
            ],
        ],
        "beforeEstimateByExtendedAddress" => [
            "listeners" => [
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "beforeSaveAddressInformation" => [
            "listeners" => [
                "Magento_InventoryInStorePickup" => [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupQuote\Model\Address\SetAddressPickupLocation",
                                      "Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup",
                                       "Magento\InventorySalesApi\Api\Data\SalesChannelInterface"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "sendClasses" => [
                        "MageWorx\Locations\Api\LocationRepositoryInterface"
                    ],
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "beforeSavePaymentInformationAndPlaceOrder" => [
            "listeners" => [
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "beforeSavePaymentInformation" => [
            "listeners" => [
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
    ];

    const filterListeners = [
        "applyGiftcard" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardCartManagementInterface"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                [
                    "module" => "Mageplaza_GiftCard",
                    "sendClasses" => ["Mageplaza\GiftCard\Helper\Checkout"],
                    "boltClass" => Mageplaza_GiftCard::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor',
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"             => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses"        => [
                        'Amasty\GiftCard\Model\GiftCardManagementFactory'
                    ],
                    "boltClass"          => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "checkClasses" => [
                        "Magento\GiftCardAccount\Model\Giftcardaccount",
                    ],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
            ],
        ],
        "filterGetBoltCollectSaleRuleDiscounts" => [
            "listeners" => [
                [
                    "module" => "Amasty_Promo",
                    "checkClasses" => ["Amasty\Promo\Api\Data\GiftRuleInterface"],
                    "boltClass" => Amasty_Promo::class,
                ],
            ],
        ],
        "collectDiscounts" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardCartManagementInterface"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                "Aheadworks_RewardPoints" => [
                    "module" => "Aheadworks_RewardPoints",
                    "sendClasses" => [
                        "Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface",
                        "Aheadworks\RewardPoints\Model\Config"
                    ],
                    "boltClass" => Aheadworks_RewardPoints::class,
                ],
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Balance",
                                      "Mirasvit\Rewards\Helper\Balance\SpendRulesList",
                                      "Mirasvit\Rewards\Model\Config",
                                      "Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc"],
                    "checkClasses" => ["Mirasvit\Rewards\Helper\Balance\SpendCartRangeData"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                [
                    "module" => "Amasty_Promo",
                    "checkClasses" => ["Amasty\Promo\Api\Data\GiftRuleInterface"],
                    "boltClass" => Amasty_Promo::class,
                ],
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => ["Mirasvit\Credit\Helper\Data",
                                      "Mirasvit\Credit\Service\Calculation",
                                      // For old version of Mirasvit Store Credit plugin,
                                      // \Magento\Framework\ObjectManagerInterface can not create instance of \Mirasvit\Credit\Api\Config\CalculationConfigInterface properly,
                                      // so we use \Mirasvit\Credit\Service\Config\CalculationConfig instead.
                                      ["Mirasvit\Credit\Api\Config\CalculationConfigInterface",
                                      "Mirasvit\Credit\Service\Config\CalculationConfig"],],
                    "boltClass" => Mirasvit_Credit::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "sendClasses" => [
                                        [
                                            "Amasty\Rewards\Model\RewardsPropertyProvider",
                                            "Amasty\Rewards\Helper\Data"
                                        ],
                                        "Amasty\Rewards\Model\ResourceModel\Quote"],
                    "boltClass" => Amasty_Rewards::class,
                ],
                [
                    "module" => "MW_RewardPoints",
                    "sendClasses" => ["MW\RewardPoints\Helper\Data",
                                      "MW\RewardPoints\Model\CustomerFactory"],
                    "boltClass" => MW_RewardPoints::class,
                ],
                [
                    "module" => "Bss_StoreCredit",
                    "sendClasses" => [
                        "Bss\StoreCredit\Helper\Data",
                        "Bss\StoreCredit\Model\ResourceModel\Credit\Collection"
                    ],
                    "boltClass" => Bss_StoreCredit::class,
                ],
                [
                    "module" => "Mageplaza_GiftCard",
                    "sendClasses" => [
                        "Mageplaza\GiftCard\Model\ResourceModel\GiftCard\CollectionFactory",
                    ],
                    "boltClass" => Mageplaza_GiftCard::class,
                ],
                [
                    "module" => "Amasty_StoreCredit",
                    "boltClass" => Amasty_StoreCredit::class,
                ],
                [
                    "module" => "Aheadworks_StoreCredit",
                    "sendClasses" => [
                        "Aheadworks\StoreCredit\Api\CustomerStoreCreditManagementInterface",
                    ],
                    "boltClass" => Aheadworks_StoreCredit::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface',
                        'Amasty\GiftCardAccount\Api\GiftCardQuoteRepositoryInterface',
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"      => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses" => [
                        'Amasty\GiftCard\Model\ResourceModel\Quote\CollectionFactory',
                    ],
                    "boltClass"   => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "sendClasses" => [
                        "Magento\GiftCardAccount\Helper\Data",
                    ],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
                [
                    "module" => "J2t_Rewardpoints",
                    "sendClasses" => [
                        "J2t\Rewardpoints\Helper\Data",
                    ],
                    "boltClass" => J2t_Rewardpoints::class,
                ],
                [
                    "module" => "BagRiders_StoreCredit",
                    "checkClasses" => ["BagRiders\StoreCredit\Api\Data\SalesFieldInterface"],
                    "sendClasses" => ["BagRiders\StoreCredit\Api\StoreCreditRepositoryInterface"],
                    "boltClass" => BagRiders_StoreCredit::class,
                ],
                "Teamwork_StoreCredit" => [
                    "module" => "Teamwork_StoreCredit",
                    "sendClasses" => [
                        "Teamwork\StoreCredit\Model\StoreCreditStorage"
                    ],
                    "boltClass" => Teamwork_StoreCredit::class,
                ],
                "Teamwork_Token" => [
                    "module" => "Teamwork_Token",
                    "checkClasses" => ["Teamwork\Token\Model\TokenStorage"],
                    "boltClass" => Teamwork_Token::class,
                ],
                "ImaginationMedia_TmwGiftCard" => [
                    "module" => "ImaginationMedia_TmwGiftCard",
                    "sendClasses" => [
                        "ImaginationMedia\TmwGiftCard\Helper\Data"
                    ],
                    "boltClass" => ImaginationMedia_TmwGiftCard::class,
                ],
                "MageWorx_RewardPoints" => [
                    "module" => "MageWorx_RewardPoints",
                    "sendClasses" => [
                        "MageWorx\RewardPoints\Api\CustomerBalanceRepositoryInterface",
                        "MageWorx\RewardPoints\Model\PointCurrencyConverter",
                        "MageWorx\RewardPoints\Helper\Data"
                    ],
                    "boltClass" => MageWorx_RewardPoints::class,
                ],
            ],
        ],
        /** @see \Bolt\Boltpay\Model\Api\UpdateDiscountTrait::verifyCouponCode */
        "loadGiftcard" => [
            "listeners" => [
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Api\GiftcardRepositoryInterface"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                [
                    "module" => "Mageplaza_GiftCard",
                    "sendClasses" => ["Mageplaza\GiftCard\Model\GiftCardFactory"],
                    "boltClass" => Mageplaza_GiftCard::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Api\GiftCardAccountRepositoryInterface',
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"      => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses" => [
                        'Amasty\GiftCard\Model\AccountFactory',
                    ],
                    "boltClass"   => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "sendClasses" => [
                        "Magento\GiftCardAccount\Model\ResourceModel\Giftcardaccount\Collection",
                    ],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
            ],
        ],
        "collectShippingDiscounts" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "checkClasses" => ["Mirasvit\Credit\Helper\Data"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
                [
                    "module" => "Mirasvit_Rewards",
                    "checkClasses" => ["Mirasvit\Rewards\Model\Config"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
            ],
        ],
        "checkMirasvitCreditAdminQuoteUsed" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "checkClasses" => ["Mirasvit\Credit\Model\Config"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
            ],
        ],
        "checkMirasvitCreditIsShippingTaxIncluded" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "sendClasses" => [["Mirasvit\Credit\Api\Config\CalculationConfigInterface",
                                      "Mirasvit\Credit\Service\Config\CalculationConfig"]],
                    "boltClass" => Mirasvit_Credit::class,
                ],
            ],
        ],
        "checkMirasvitRewardsIsShippingIncluded" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Model\Config"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
            ],
        ],
        "getAdditionalJS" => [
            "listeners" => [
                [
                    "module" => "MW_RewardPoints",
                    "checkClasses" => ["MW\RewardPoints\Helper\Data"],
                    "boltClass" => MW_RewardPoints::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "boltClass" => Amasty_Rewards::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "checkClasses" => ["Magento\GiftCardAccount\Model\Giftcardaccount"],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
                [
                    "module" => "J2t_Rewardpoints",
                    "checkClasses" => [
                        "J2t\Rewardpoints\Helper\Data",
                    ],
                    "boltClass" => J2t_Rewardpoints::class,
                ],
            ],
        ],
        "filterApplyingGiftCardCode" => [
            "listeners" => [
                [
                    "module" => "Mageplaza_GiftCard",
                    "sendClasses" => ["Mageplaza\GiftCard\Helper\Checkout"],
                    "boltClass" => Mageplaza_GiftCard::class,
                ],
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Model\Service\GiftcardCartService"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor'
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"             => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses"        => [
                        'Amasty\GiftCard\Model\GiftCardManagement'
                    ],
                    "boltClass"          => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "checkClasses" => ["Magento\GiftCardAccount\Model\Giftcardaccount"],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
            ],
        ],
        'filterRemovingGiftCardCode' => [
            "listeners" => [
                [
                    "module" => "Mageplaza_GiftCard",
                    "sendClasses" => ["Mageplaza\GiftCard\Helper\Checkout"],
                    "boltClass" => Mageplaza_GiftCard::class,
                ],
                [
                    "module" => "Aheadworks_Giftcard",
                    "sendClasses" => ["Aheadworks\Giftcard\Model\Service\GiftcardCartService"],
                    "boltClass" => Aheadworks_Giftcard::class,
                ],
                'Amasty Giftcard V2' => [
                    "module"      => "Amasty_GiftCardAccount",
                    "sendClasses" => [
                        'Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardCartProcessor'
                    ],
                    "boltClass"   => Amasty_GiftCardAccount::class,
                ],
                'Amasty Giftcard V1' => [
                    "module"             => "Amasty_GiftCard",
                    "checkClasses"       => self::AMASTY_GIFTCARD_V1_CHECK_CLASSES,
                    "sendClasses"        => [
                        'Amasty\GiftCard\Model\GiftCardManagement'
                    ],
                    "boltClass"          => Amasty_GiftCard::class,
                ],
                [
                    "module" => "Magento_GiftCardAccount",
                    "checkClasses" => ["Magento\GiftCardAccount\Model\Giftcardaccount"],
                    "boltClass" => Magento_GiftCardAccount::class,
                ],
            ],
        ],
        "getAdditionalHtml" => [
            "listeners" => [
                [
                    "module" => "Listrak_Remarketing",
                    "boltClass" => Listrak_Remarketing::class,
                ],
            ],
        ],
        "getOnEmailEnter" => [
            "listeners" => [
                [
                    "module" => "Listrak_Remarketing",
                    "boltClass" => Listrak_Remarketing::class,
                ],
            ],
        ],
        "filterApplyExternalQuoteData" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Balance",
                                      "Mirasvit\Rewards\Helper\Balance\SpendRulesList",
                                      "Mirasvit\Rewards\Model\Config",
                                      "Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
            ],
        ],
        "filterProcessLayout" => [
            "listeners" => [
                [
                    "module" => "Amasty_StoreCredit",
                    "boltClass" => Amasty_StoreCredit::class,
                ],
                [
                    "module" => "Magento_CustomerBalance",
                    "boltClass" => \Bolt\Boltpay\ThirdPartyModules\Magento\CustomerBalance::class,
                ],
                [
                    "module" => "Magento_Reward",
                    "boltClass" => \Bolt\Boltpay\ThirdPartyModules\Magento\Reward::class,
                ],
                [
                    "module" => "BagRiders_StoreCredit",
                    "boltClass" => BagRiders_StoreCredit::class,
                ],
            ],
        ],
        "filterMinicartAddonsLayout" => [
            "listeners" => [
                [
                    "module" => "Magento_Reward",
                    "boltClass" => \Bolt\Boltpay\ThirdPartyModules\Magento\Reward::class,
                ],
            ],
        ],
        "filterVerifyAppliedStoreCredit" => [
            "listeners" => [
                [
                    "module" => "Amasty_StoreCredit",
                    "checkClasses" => ["Amasty\StoreCredit\Api\Data\SalesFieldInterface"],
                    "boltClass" => Amasty_StoreCredit::class,
                ],
                [
                    "module" => "Aheadworks_StoreCredit",
                    "checkClasses" => ["Aheadworks\StoreCredit\Model\TransactionRepository"],
                    "boltClass" => Aheadworks_StoreCredit::class,
                ],
                [
                    "module" => "Aheadworks_RewardPoints",
                    "boltClass" => Aheadworks_RewardPoints::class,
                ],
                [
                    "module" => "MW_RewardPoints",
                    "checkClasses" => ["MW\RewardPoints\Helper\Data"],
                    "boltClass" => MW_RewardPoints::class,
                ],
                [
                    "module" => "Amasty_Rewards",
                    "checkClasses" => ["Amasty\Rewards\Api\CheckoutRewardsManagementInterface"],
                    "boltClass" => Amasty_Rewards::class,
                ],
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Helper\Purchase",
                                      "Mirasvit\Rewards\Helper\Balance",
                                      "Mirasvit\Rewards\Helper\Balance\SpendRulesList",
                                      "Mirasvit\Rewards\Model\Config",
                                      "Mirasvit\Rewards\Helper\Balance\Spend\RuleQuoteSubtotalCalc"],
                    "checkClasses" => ["Mirasvit\Rewards\Helper\Balance\SpendCartRangeData"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                [
                    "module" => "Mirasvit_Credit",
                    "checkClasses" => ["Mirasvit\Credit\Helper\Data"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
                [
                    "module" => "J2t_Rewardpoints",
                    "checkClasses" => [
                        "J2t\Rewardpoints\Helper\Data",
                    ],
                    "boltClass" => J2t_Rewardpoints::class,
                ],
                [
                    "module" => "BagRiders_StoreCredit",
                    "checkClasses" => ["BagRiders\StoreCredit\Api\Data\SalesFieldInterface"],
                    "boltClass" => BagRiders_StoreCredit::class,
                ],
                [
                    "module" => "Magento_CustomerBalance",
                    "checkClasses" => ["Magento\CustomerBalance\Model\Balance"],
                    "boltClass" => Magento_CustomerBalance::class,
                ],
            ]
        ],
        'filterSkipValidateShippingForProcessNewOrder' => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Rewards",
                    "sendClasses" => ["Mirasvit\Rewards\Model\Config"],
                    "boltClass" => Mirasvit_Rewards::class,
                ],
                'MW_RewardPoints' => [
                    'module'      => 'MW_RewardPoints',
                    "sendClasses" => [
                        "MW\RewardPoints\Helper\Data",
                    ],
                    'boltClass'   => MW_RewardPoints::class,
                ],
            ]
        ],
        "filterShippingAmount" => [
            'listeners' => [
                'MW_RewardPoints' => [
                    'module'      => 'MW_RewardPoints',
                    "sendClasses" => [
                        "MW\RewardPoints\Helper\Data",
                        "MW\RewardPoints\Model\CustomerFactory"
                    ],
                    'boltClass'   => MW_RewardPoints::class,
                ],
            ],
        ],
        "adjustShippingAmountInTaxEndPoint" => [
            'listeners' => [
                'MW_RewardPoints' => [
                    'module'      => 'MW_RewardPoints',
                    "sendClasses" => [
                        "MW\RewardPoints\Helper\Data",
                        "MW\RewardPoints\Model\CustomerFactory"
                    ],
                    'boltClass'   => MW_RewardPoints::class,
                ],
            ],
        ],
        "getAdditionalInvalidateBoltCartJavascript" => [
            "listeners" => [
                [
                    "module" => "Mirasvit_Credit",
                    "checkClasses" => ["Mirasvit\Credit\Helper\Data"],
                    "boltClass" => Mirasvit_Credit::class,
                ],
            ],
        ],
        "collectSessionData" => [
            "listeners" => [
                [
                    "module"      => "IDme_GroupVerification",
                    "boltClass"   => IDme_GroupVerification::class,
                ],
            ],
        ],
        "verifyRuleTimeFrame" => [
            "listeners" => [
                [
                    "module"      => "Magento_SalesRuleStaging",
                    "boltClass"   => Magento_SalesRuleStaging::class,
                ],
            ],
        ],
        "collectCartDiscountJsLayout" => [
            "listeners" => [
                "Aheadworks_RewardPoints" => [
                    "module"      => "Aheadworks_RewardPoints",
                    "sendClasses" => ["Aheadworks\RewardPoints\Api\CustomerRewardPointsManagementInterface"],
                    "boltClass"   => Aheadworks_RewardPoints::class,
                ],
                "MageWorx_RewardPoints" => [
                    "module"      => "MageWorx_RewardPoints",
                    "sendClasses" => ["MageWorx\RewardPoints\Helper\Data"],
                    "boltClass"   => MageWorx_RewardPoints::class,
                ],
                "Aheadworks_StoreCredit" => [
                    "module"      => "Aheadworks_StoreCredit",
                    "sendClasses" => ["Aheadworks\StoreCredit\Api\CustomerStoreCreditManagementInterface"],
                    "boltClass"   => Aheadworks_StoreCredit::class,
                ],
            ]
        ],
        "saveSessionData" => [
            "listeners" => [
                [
                    "module" => "Mageside_CustomShippingPrice",
                    "checkClasses" => ["Mageside\CustomShippingPrice\Model\Carrier\CustomShipping"],
                    "boltClass" => Mageside_CustomShippingPrice::class,
                ],
                [
                    "module" => "MW_Affiliate",
                    "sendClasses" => ["MW\Affiliate\Helper\Data"],
                    "boltClass" => MW_Affiliate::class,
                ],
                [
                    "module" => "Amasty_Affiliate",
                    "boltClass" => Amasty_Affiliate::class,
                ],
            ],
        ],
        "getCartCacheIdentifier" => [
            "listeners" => [
                [
                    "module" => "MW_Affiliate",
                    "checkClasses" => ["MW\Affiliate\Helper\Data"],
                    "boltClass" => MW_Affiliate::class,
                ],
            ],
        ],
        "getShipToStoreOptions" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupApi\Model\SearchRequestBuilderInterface",
                                      "Magento\InventoryInStorePickupApi\Api\GetPickupLocationsInterface",
                                      "Magento\InventoryInStorePickupApi\Api\Data\SearchRequest\ProductInfoInterfaceFactory",
                                      "Magento\InventoryInStorePickupApi\Api\Data\SearchRequestExtensionFactory",
                                      "Magento\InventoryInStorePickup\Model\SearchRequest\Area\GetDistanceToSources"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup",
                                       "Magento\InventorySalesApi\Api\Data\SalesChannelInterface"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "SomethingDigital_InStorePickupBoltIntegration" => [
                    "module" => "SomethingDigital_InStorePickupBoltIntegration",
                    "sendClasses" => [
                        "SomethingDigital\InStorePickupBoltIntegration\Helper\PickupStoreChecker",
                        "Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver"
                    ],
                    "checkClasses" => [
                        "Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver"
                    ],
                    "boltClass" => SomethingDigital_InStorePickupBoltIntegration::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "sendClasses" => [
                        "MageWorx\StoreLocator\Helper\Data"
                    ],
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "getShipToStoreCarrierMethodCodes" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "SomethingDigital_InStorePickupBoltIntegration" => [
                    "module" => "SomethingDigital_InStorePickupBoltIntegration",
                    "sendClasses" => [
                        "SomethingDigital\InStorePickupBoltIntegration\Helper\PickupStoreChecker",
                    ],
                    "checkClasses" => [
                        "Magedelight\Storepickup\Model\Observer\SaveDeliveryDateToOrderObserver"
                    ],
                    "boltClass" => SomethingDigital_InStorePickupBoltIntegration::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        "filterCart" => [
            'listeners' => [
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'sendClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'boltClass'   => Amasty_Extrafee::class,
                ]
            ]
        ],
        "filterCartItems" => [
            'listeners' => [
                'Magecomp_Extrafee' => [
                    'module'      => 'Magecomp_Extrafee',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
                'Brainvire_Engraving' => [
                    'module'      => 'Brainvire_Engraving',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'sendClasses' => ['Route\Route\Helper\Data'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'sendClasses' => [
                        'Amasty\Extrafee\Model\ResourceModel\ExtrafeeQuote\CollectionFactory',
                        'Amasty\Extrafee\Model\TotalsInformationManagement'
                    ],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'sendClasses' => ['MageWorx\Donations\Helper\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        "filterTransactionBeforeOrderCreateValidation" => [
            'listeners' => [
                'Magecomp_Extrafee' => [
                    'module'      => 'Magecomp_Extrafee',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
                'Brainvire_Engraving' => [
                    'module'      => 'Brainvire_Engraving',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
            ],
        ],
        "filterCartBeforeLegacyShippingAndTax" => [
            'listeners' => [
                'Magecomp_Extrafee' => [
                    'module'      => 'Magecomp_Extrafee',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
                'Brainvire_Engraving' => [
                    'module'      => 'Brainvire_Engraving',
                    'boltClass'   => Magecomp_Extrafee::class,
                ],
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        "filterCartBeforeSplitShippingAndTax" => [
            'listeners' => [
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        "filterCartBeforeCreateOrder" => [
            'listeners' => [
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        "isInStorePickupShipping" => [
            "listeners" => [
                [
                    "module" => "Magento_InventoryInStorePickup",
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
        'filterCartItemsAdditionalAttributeValue' => [
            'listeners' => [
                'Amasty_Preorder' => [
                    'module' => 'Amasty_Preorder',
                    'sendClasses' => ['Amasty\Preorder\Helper\Data'],
                    'boltClass' => Amasty_Preorder::class,
                ],
            ],
        ],
        "getAdditionalQuoteTotalsConditions" => [
            "listeners" => [
                "MageWorx_RewardPoints" => [
                    "module" => "MageWorx_RewardPoints",
                    "sendClasses" => ["MageWorx\RewardPoints\Helper\Data"],
                    "boltClass" => MageWorx_RewardPoints::class,
                ],
            ],
        ],
        'filterAddItemBeforeUpdateCart' => [
            "listeners" => [
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'sendClasses' => [
                        'Amasty\Extrafee\Model\TotalsInformationManagement',
                        'Amasty\Extrafee\Model\ResourceModel\ExtrafeeQuote\CollectionFactory'
                    ],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'sendClasses' => [
                        'MageWorx\Donations\Helper\Donation',
                        'MageWorx\Donations\Model\ResourceModel\Charity\CollectionFactory'
                    ],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        'filterRemoveItemBeforeUpdateCart' => [
            "listeners" => [
                'Route_Route' => [
                    'module'      => 'Route_Route',
                    'checkClasses' => ['Route\Route\Model\Quote\Total\RouteFee'],
                    'boltClass'   => Route_Route::class,
                ],
                'Amasty_Extrafee' => [
                    'module'      => 'Amasty_Extrafee',
                    'checkClasses' => ['Amasty\Extrafee\Model\FeesInformationManagement'],
                    'sendClasses' => [
                        'Amasty\Extrafee\Model\TotalsInformationManagement',
                        'Amasty\Extrafee\Model\ResourceModel\ExtrafeeQuote\CollectionFactory'
                    ],
                    'boltClass'   => Amasty_Extrafee::class,
                ],
                'MageWorx_Donations' => [
                    'module'      => 'MageWorx_Donations',
                    'checkClasses' => ['MageWorx\Donations\Model\Donation'],
                    'sendClasses' => ['\MageWorx\Donations\Helper\Donation'],
                    'boltClass'   => MageWorx_Donations::class,
                ]
            ],
        ],
        'loadCouponCodeData' => [
            "listeners" => [
                'Mexbs_Tieredcoupon' => [
                    'module'      => 'Mexbs_Tieredcoupon',
                    'sendClasses' => ['Mexbs\Tieredcoupon\Helper\Data'],
                    'boltClass'   => Mexbs_Tieredcoupon::class,
                ],
            ],
        ],
        'isValidCouponObj' => [
            "listeners" => [
                'Mexbs_Tieredcoupon' => [
                    'module'      => 'Mexbs_Tieredcoupon',
                    'sendClasses' => ['Mexbs\Tieredcoupon\Helper\Data'],
                    'boltClass'   => Mexbs_Tieredcoupon::class,
                ],
            ],
        ],
        'filterApplyingCouponCode' => [
            "listeners" => [
                'Mexbs_Tieredcoupon' => [
                    'module'      => 'Mexbs_Tieredcoupon',
                    'sendClasses' => ['Mexbs\Tieredcoupon\Helper\Data'],
                    'boltClass'   => Mexbs_Tieredcoupon::class,
                ],
            ],
        ],
        'getCouponRelatedRule' => [
            "listeners" => [
                'Mexbs_Tieredcoupon' => [
                    'module'      => 'Mexbs_Tieredcoupon',
                    'sendClasses' => ['Mexbs\Tieredcoupon\Helper\Data'],
                    'boltClass'   => Mexbs_Tieredcoupon::class,
                ],
            ],
        ],
        'beforeGetOrderByIdProcessNewOrder' => [
            "listeners" => [
                'Amasty Giftcard V2.5' => [
                    "module"       => "Amasty_GiftCardAccount",
                    "checkClasses" => self::AMASTY_GIFTCARD_V25_CHECK_CLASSES,
                    'sendClasses'  => ['Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Handlers\SaveHandler'],
                    "boltClass"    => Amasty_GiftCardAccount::class,
                ],
                'MW_Affiliate' => [
                    "module" => "MW_Affiliate",
                    "sendClasses" => ["MW\Affiliate\Helper\Data",
                                      "MW\Affiliate\Observer\SalesOrderAfter"],
                    "boltClass" => MW_Affiliate::class,
                ],
                'Amasty_Affiliate' => [
                    "module" => "Amasty_Affiliate",
                    "sendClasses" => ["Amasty\Affiliate\Observer\SalesOrderAfterPlaceObserver"],
                    "boltClass" => Amasty_Affiliate::class,
                ],
            ],
        ],
        'filterQuoteDiscountDetails' => [
            "listeners" => [
                'Mexbs_Tieredcoupon' => [
                    'module'      => 'Mexbs_Tieredcoupon',
                    'sendClasses' => ['Mexbs\Tieredcoupon\Helper\Data'],
                    'boltClass'   => Mexbs_Tieredcoupon::class,
                ],
            ],
        ],
        'filterShouldDisableBoltCheckout' => [
            "listeners" => [
                'Magento_CompanyPayment' => [
                    'module'      => 'Magento_CompanyPayment',
                    'sendClasses' => ['Magento\CompanyPayment\Model\Payment\Checks\CanUseForCompany'],
                    'boltClass'   => Magento_CompanyPayment::class,
                ],
            ],
        ],
        'filterCartItemsProperties' => [
            "listeners" => [
                [
                    "module" => "Magento_GiftCard",
                    "checkClasses" => [
                        "Magento\GiftCard\Model\Giftcard",
                    ],
                    "boltClass" => Magento_GiftCard::class,
                ],
            ],
        ],
        "afterEstimateByExtendedAddress" => [
            "listeners" => [
                "Magento_InStorePickupShipping" => [
                    "module" => "Magento_InventoryInStorePickup",
                    "sendClasses" => ["Magento\InventoryInStorePickupApi\Model\SearchRequestBuilderInterface",
                                      "Magento\InventoryInStorePickupApi\Api\GetPickupLocationsInterface",
                                      "Magento\InventoryInStorePickupApi\Api\Data\SearchRequest\ProductInfoInterfaceFactory",
                                      "Magento\InventoryInStorePickupApi\Api\Data\SearchRequestExtensionFactory",
                                      "Magento\InventoryInStorePickup\Model\SearchRequest\Area\GetDistanceToSources"],
                    "checkClasses" => ["Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup",
                                       "Magento\InventorySalesApi\Api\Data\SalesChannelInterface"],
                    "boltClass" => Magento_InStorePickupShipping::class,
                ],
                "MageWorx_Pickup" => [
                    "module" => "MageWorx_Pickup",
                    "sendClasses" => [
                        "MageWorx\StoreLocator\Helper\Data"
                    ],
                    "checkClasses" => [
                        "MageWorx\Pickup\Model\Carrier\PickupShipping"
                    ],
                    "boltClass" => MageWorx_Pickup::class,
                ],
            ],
        ],
    ];

    /**
     * @var array Classes used to verify that Amasty Giftcard module version is 1.x
     */
    const AMASTY_GIFTCARD_V1_CHECK_CLASSES = [
        'Amasty\GiftCard\Model\GiftCardManagement',
        'Amasty\GiftCard\Model\AccountFactory',
        'Amasty\GiftCard\Model\ResourceModel\Quote\CollectionFactory',
        'Amasty\GiftCard\Api\CodeRepositoryInterface',
        'Amasty\GiftCard\Api\AccountRepositoryInterface',
    ];

    /**
     * @var array Classes used to verify that Amasty Giftcard module version >= 2.5.0
     */
    const AMASTY_GIFTCARD_V25_CHECK_CLASSES = [
        'Amasty\GiftCardAccount\Model\GiftCardAccount\GiftCardAccountTransactionProcessor',
    ];

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * ThirdPartyModuleFactory constructor.
     *
     * @param \Magento\Framework\Module\Manager         $moduleManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        Bugsnag $bugsnag
    ) {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->bugsnag = $bugsnag;
    }

    /**
     * Check if module enables and neccessary classes exist
     * return [$result, $sendClasses]
     * bool $result true if we should run the method
     * array $sendClasses array of classed we should pass into the method
     */
    private function prepareForListenerRun($listener, $createInstance = true)
    {
        if (!$this->isModuleAvailable($listener["module"]) && empty($listener["alwaysRun"])) {
            return [false, null];
        }
        if (isset($listener["checkClasses"])) {
            foreach ($listener["checkClasses"] as $classNameItem) {
                // Some merchants still use legacy version of third-party plugin,
                // so there are cases that the class does not exist,
                // then we use sub-array to include classes for supported versions.
                $classNames = is_array($classNameItem) ? $classNameItem : [$classNameItem];
                $existClasses = array_filter($classNames, function ($className) {
                    return $this->doesClassExist($className);
                });
                if (empty($existClasses)) {
                    return [false,null];
                }
            }
        }
        $sendClasses = [];
        if (isset($listener["sendClasses"])) {
            foreach ($listener["sendClasses"] as $classNameItem) {
                // Some merchants still use legacy version of third-party plugin,
                // so there are cases that the class does not exist or its instance can not be created,
                // then we use sub-array to include classes for supported versions.
                $classNames = is_array($classNameItem) ? $classNameItem : [$classNameItem];
                $classInstance = null;
                foreach ($classNames as $className) {
                    // Once the class instance is created, no need to process the rest.
                    if (!empty($classInstance)) {
                        break;
                    }
                    $classInstance = $this->doesClassExist($className) ? $this->objectManager->get($className) : null;
                }

                if (empty($classInstance)) {
                    return [false,null];
                }

                $sendClasses[] = $classInstance;
            }
        }
        return [true, $sendClasses];
    }

    /**
     * Run filter
     *
     * Call all filter listeners that relates to existing module and if necessary classes exist
     */
    public function runFilter($filterName, $result, ...$arguments)
    {
        if (!isset(static::filterListeners[$filterName])) {
            return;
        }
        try {
            foreach (static::filterListeners[$filterName]["listeners"] as $listener) {
                // By default, we create instance for running filter
                $createInstance = isset($listener["createInstance"]) ? $listener["createInstance"] : true;
                list ($active, $sendClasses) = $this->prepareForListenerRun($listener, $createInstance);
                if (!$active) {
                    continue;
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                if ($createInstance && $sendClasses) {
                    $result = $boltClass->$filterName($result, ...$sendClasses, ...$arguments);
                } else {
                    $result = $boltClass->$filterName($result, ...$arguments);
                }
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        } finally {
            return $result;
        }
    }

    /**
     * Dispatch event
     *
     * Call all listeners that relates to existing module and if necessary classes exist
     */
    public function dispatchEvent($eventName, ...$arguments)
    {
        if (!isset(static::eventListeners[$eventName])) {
            return;
        }
        try {
            foreach (static::eventListeners[$eventName]["listeners"] as $listener) {
                list ($active, $sendClasses) = $this->prepareForListenerRun($listener);
                if (!$active) {
                    continue;
                }
                $boltClass = $this->objectManager->get($listener["boltClass"]);
                if ($sendClasses) {
                    $boltClass->$eventName(...$sendClasses, ...$arguments);
                } else {
                    $boltClass->$eventName(...$arguments);
                }
            }
        } catch (Exception $e) {
            $this->bugsnag->notifyException($e);
        }
    }

    /**
     * Check whether the module is available (installed and enabled)
     * @return bool
     */
    public function isModuleAvailable($moduleName)
    {
        return $this->moduleManager->isEnabled($moduleName);
    }

    /**
     * Check whether the class exists
     * @return bool
     */
    private function doesClassExist($className)
    {
        ///////////////////////////////////////////////////////////////
        // Due to a known bug https://github.com/magento/magento2/pull/21435,
        // the autoloader throws an exception on class_exists.
        // Add try-catch block to avoid uncaught exceptions during autoloading.
        // Return false instead if any uncaught exceptions.
        ///////////////////////////////////////////////////////////////
        try {
            if (substr($className, -9) === "Interface") {
                return interface_exists($className);
            } else {
                return class_exists($className);
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
