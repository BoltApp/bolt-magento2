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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Model\Api;

use Bolt\Boltpay\Helper\Bugsnag;
use Bolt\Boltpay\Helper\Cart as CartHelper;
use Bolt\Boltpay\Helper\Config as ConfigHelper;
use Bolt\Boltpay\Helper\Hook as HookHelper;
use Bolt\Boltpay\Helper\Log as LogHelper;
use Bolt\Boltpay\Helper\MetricsClient;
use Bolt\Boltpay\Helper\Session as SessionHelper;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Bolt\Boltpay\Helper\Order as OrderHelper;
use Bolt\Boltpay\Exception\BoltException;
use Bolt\Boltpay\Model\Api\CreateOrder;

/**
 * Class CreateOrderTest
 *
 * @package Bolt\Boltpay\Test\Unit\Model\Api
 */
class CreateOrderTest extends TestCase
{
    const STORE_ID = 1;
    const MINIMUM_ORDER_AMOUNT = 50;

    /**
     * @var HookHelper
     */
    private $hookHelper;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var LogHelper
     */
    private $logHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Bugsnag
     */
    private $bugsnag;

    /**
     * @var MetricsClient
     */
    private $metricsClient;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var BackendUrl
     */
    private $backendUrl;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var SessionHelper
     */
    private $sessionHelper;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $currentMock;

    /**
     * @var Quote
     */
    private $quoteMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->initRequiredMocks();
        $this->initCurrentMock();
    }

    private function initRequiredMocks()
    {
        $this->hookHelper = $this->createMock(HookHelper::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->logHelper = $this->createMock(LogHelper::class);
        $this->request = $this->createMock(Request::class);
        $this->bugsnag = $this->createMock(Bugsnag::class);
        $this->metricsClient = $this->createMock(MetricsClient::class);
        $this->response = $this->createMock(Response::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->backendUrl = $this->createMock(BackendUrl::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->sessionHelper = $this->createMock(SessionHelper::class);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->cartHelper = $objectManager->get(CartHelper::class);

        $this->quoteMock = $this->createPartialMock(
            Quote::class,
            [
                'validateMinimumAmount',
                'getGrandTotal',
                'getStoreId',
            ]);
    }

    private function initCurrentMock()
    {
        $this->currentMock = $this->getMockBuilder(CreateOrder::class)
            ->setConstructorArgs([
                $this->hookHelper,
                $this->orderHelper,
                $this->cartHelper,
                $this->logHelper,
                $this->request,
                $this->bugsnag,
                $this->metricsClient,
                $this->response,
                $this->url,
                $this->backendUrl,
                $this->configHelper,
                $this->stockRegistry,
                $this->sessionHelper
            ])
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * @test
     */
    public function validateMinimumAmount_valid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(true);
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }

    /**
     * @test
     */
    public function validateMinimumAmount_invalid()
    {
        $this->quoteMock->expects(static::once())->method('validateMinimumAmount')->willReturn(false);
        $this->quoteMock->expects(static::once())->method('getStoreId')->willReturn(static::STORE_ID);
        $this->configHelper->expects(static::once())->method('getMinimumOrderAmount')->with(static::STORE_ID)
            ->willReturn(static::MINIMUM_ORDER_AMOUNT);
        $this->bugsnag->expects(static::once())->method('registerCallback');
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_MINIMUM_PRICE_NOT_MET);
        $this->expectExceptionMessage(
            sprintf(
                'The minimum order amount: %s has not being met.', static::MINIMUM_ORDER_AMOUNT
            )
        );
        $this->currentMock->validateMinimumAmount($this->quoteMock);
    }

    /**
     * @test
     */
    public function validateTotalAmount_valid()
    {
        $this->quoteMock->expects(static::once())->method('getGrandTotal')->willReturn(28.82);
        $this->currentMock->validateTotalAmount($this->quoteMock, $this->getTransaction());
    }

    /**
     * @test
     */
    public function validateTotalAmount_invalid()
    {
        $this->quoteMock->expects(static::once())->method('getGrandTotal')->willReturn(28.81);
        $this->bugsnag->expects(static::once())->method('registerCallback');
        $this->expectException(BoltException::class);
        $this->expectExceptionCode(\Bolt\Boltpay\Model\Api\CreateOrder::E_BOLT_GENERAL_ERROR);
        $this->expectExceptionMessage('Total amount does not match.');
        $this->currentMock->validateTotalAmount($this->quoteMock, $this->getTransaction());
    }

    private function getTransaction()
    {
        $transaction = <<<TRANSACTION
        {
            "id": "TAj57ALHgDNXZ",
            "type": "cc_payment",
            "date": 1566923343111,
            "reference": "3JRC-BVGG-CNBD",
            "status": "completed",
            "from_consumer": {
                "id": "CAiC6LhLAUMq7",
                "first_name": "Bolt",
                "last_name": "Team",
                "avatar": {
                    "domain": "img-sandbox.bolt.com",
                    "resource": "default.png"
                },
                "phones": [
                    {
                        "id": "",
                        "number": "+1 7894566548",
                        "country_code": "1",
                        "status": "",
                        "priority": ""
                    },
                    {
                        "id": "PAfnA3HiQRZpZ",
                        "number": "+1 789 456 6548",
                        "country_code": "1",
                        "status": "pending",
                        "priority": "primary"
                    }
                ],
                "emails": [
                    {
                        "id": "",
                        "address": "daniel.dragic@bolt.com",
                        "status": "",
                        "priority": ""
                    },
                    {
                        "id": "EA8hRQHRpHx6Z",
                        "address": "daniel.dragic@bolt.com",
                        "status": "pending",
                        "priority": "primary"
                    }
                ]
            },
            "to_consumer": {
                "id": "CAfR8NYVXrrLb",
                "first_name": "Leon",
                "last_name": "McCottry",
                "avatar": {
                    "domain": "img-sandbox.bolt.com",
                    "resource": "default.png"
                },
                "phones": [
                    {
                        "id": "PAgDzbZW8iwZ7",
                        "number": "5555559647",
                        "country_code": "1",
                        "status": "active",
                        "priority": "primary"
                    }
                ],
                "emails": [
                    {
                        "id": "EA4iyW8c7Mues",
                        "address": "leon+magento2@bolt.com",
                        "status": "active",
                        "priority": "primary"
                    }
                ]
            },
            "from_credit_card": {
                "id": "CA8E8FedBJNfM",
                "description": "default card",
                "last4": "1111",
                "bin": "411111",
                "expiration": 1575158400000,
                "network": "visa",
                "token_type": "vantiv",
                "priority": "listed",
                "display_network": "Visa",
                "icon_asset_path": "img/issuer-logos/visa.png",
                "status": "transient",
                "billing_address": {
                    "id": "AA2L6bxABJBn4",
                    "street_address1": "1235D Howard Street",
                    "locality": "San Francisco",
                    "region": "California",
                    "postal_code": "94103",
                    "country_code": "US",
                    "country": "United States",
                    "name": "Bolt Team",
                    "first_name": "Bolt",
                    "last_name": "Team",
                    "company": "Bolt",
                    "phone_number": "7894566548",
                    "email_address": "daniel.dragic@bolt.com"
                }
            },
            "amount": {
                "amount": 2882,
                "currency": "USD",
                "currency_symbol": "$"
            },
            "authorization": {
                "status": "succeeded",
                "reason": "none"
            },
            "capture": {
                "id": "CAfi8PprxApDF",
                "status": "succeeded",
                "amount": {
                    "amount": 2882,
                    "currency": "USD",
                    "currency_symbol": "$"
                },
                "splits": [
                    {
                        "amount": {
                            "amount": 2739,
                            "currency": "USD",
                            "currency_symbol": "$"
                        },
                        "type": "net"
                    },
                    {
                        "amount": {
                            "amount": 114,
                            "currency": "USD",
                            "currency_symbol": "$"
                        },
                        "type": "processing_fee"
                    },
                    {
                        "amount": {
                            "amount": 29,
                            "currency": "USD",
                            "currency_symbol": "$"
                        },
                        "type": "bolt_fee"
                    }
                ]
            },
            "captures": [
                {
                    "id": "CAfi8PprxApDF",
                    "status": "succeeded",
                    "amount": {
                        "amount": 2882,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "splits": [
                        {
                            "amount": {
                                "amount": 2739,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "type": "net"
                        },
                        {
                            "amount": {
                                "amount": 114,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "type": "processing_fee"
                        },
                        {
                            "amount": {
                                "amount": 29,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "type": "bolt_fee"
                        }
                    ]
                }
            ],
            "merchant_division": {
                "id": "MAd7pWDqT9JzX",
                "merchant_id": "MAe3Hc1YXENzq",
                "public_id": "NwQxY8yKNDiL",
                "description": "bolt-magento2 - full",
                "logo": {
                    "domain": "img-sandbox.bolt.com",
                    "resource": "bolt-magento2_-_full_logo_1559750957154518171.png"
                },
                "platform": "magento",
                "hook_url": "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/order/manage",
                "hook_type": "bolt",
                "shipping_and_tax_url": "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/shipping/methods",
                "create_order_url": "https://bane-magento2.guaranteed.site/rest/V1/bolt/boltpay/order/create"
            },
            "merchant": {
                "description": "Guaranteed Site - Magento2 Sandbox",
                "time_zone": "America/Los_Angeles",
                "public_id": "aksPFmo1MoeQ",
                "processor": "vantiv",
                "processor_linked": true
            },
            "indemnification_decision": "indemnified",
            "indemnification_reason": "risk_engine_approved",
            "last_viewed_utc": 0,
            "splits": [
                {
                    "amount": {
                        "amount": 2739,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "type": "net"
                },
                {
                    "amount": {
                        "amount": 114,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "type": "processing_fee"
                },
                {
                    "amount": {
                        "amount": 29,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "type": "bolt_fee"
                }
            ],
            "auth_verification_status": "",
            "order": {
                "token": "d938cebc0402ec32eded0ad19bf899e0e098d45206a8b0332a8540987f6bec10",
                "cart": {
                    "order_reference": "21673",
                    "display_id": "000000422 / 21673",
                    "currency": {
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "subtotal_amount": {
                        "amount": 2200,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "total_amount": {
                        "amount": 2882,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "tax_amount": {
                        "amount": 182,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "shipping_amount": {
                        "amount": 500,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "discount_amount": {
                        "amount": 0,
                        "currency": "USD",
                        "currency_symbol": "$"
                    },
                    "billing_address": {
                        "id": "AA4d7NHaYycS4",
                        "street_address1": "1235D Howard Street",
                        "locality": "San Francisco",
                        "region": "California",
                        "postal_code": "94103",
                        "country_code": "US",
                        "country": "United States",
                        "name": "Bolt Team",
                        "first_name": "Bolt",
                        "last_name": "Team",
                        "company": "Bolt",
                        "phone_number": "7894566548",
                        "email_address": "daniel.dragic@bolt.com"
                    },
                    "items": [
                        {
                            "reference": "1547",
                            "name": "Radiant Tee",
                            "description": "So light and comfy, you'll love the Radiant Tee's organic fabric, feel, performance and style.",
                            "total_amount": {
                                "amount": 2200,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "unit_price": {
                                "amount": 2200,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "quantity": 1,
                            "sku": "WS12-XS-Blue",
                            "image_url": "https://bane-magento2.guaranteed.site/pub/media/catalog/product/cache/7fa208cd9fd2e4c5b9a620e676576640/w/s/ws12-blue_main_1.jpg",
                            "type": "physical",
                            "taxable": true,
                            "properties": [
                                {
                                    "name": "Color",
                                    "value": "Blue"
                                },
                                {
                                    "name": "Size",
                                    "value": "XS"
                                }
                            ]
                        }
                    ],
                    "shipments": [
                        {
                            "shipping_address": {
                                "id": "AAai76YYHWQby",
                                "street_address1": "1235D Howard Street",
                                "locality": "San Francisco",
                                "region": "California",
                                "postal_code": "94103",
                                "country_code": "US",
                                "country": "United States",
                                "name": "Bolt Team",
                                "first_name": "Bolt",
                                "last_name": "Team",
                                "company": "Bolt",
                                "phone_number": "7894566548",
                                "email_address": "daniel.dragic@bolt.com"
                            },
                            "shipping_method": "unknown",
                            "service": "Flat Rate - Fixed",
                            "cost": {
                                "amount": 500,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "tax_amount": {
                                "amount": 182,
                                "currency": "USD",
                                "currency_symbol": "$"
                            },
                            "reference": "flatrate_flatrate"
                        }
                    ]
                },
                "external_data": {}
            },
            "timeline": [
                {
                    "date": 1567011727217,
                    "type": "note",
                    "note": "Bolt Settled Order",
                    "visibility": "merchant"
                },
                {
                    "date": 1566923457810,
                    "type": "note",
                    "note": "Guaranteed Site - Magento2 Sandbox Captured Order",
                    "visibility": "merchant"
                },
                {
                    "date": 1566923431003,
                    "type": "note",
                    "note": "Bolt Approved Order",
                    "visibility": "merchant"
                },
                {
                    "date": 1566923344843,
                    "type": "note",
                    "note": "Authorized Order",
                    "consumer": {
                        "id": "CAi8cQ5u5vL5P",
                        "first_name": "Bolt",
                        "last_name": "Team",
                        "avatar": {
                            "domain": "img-sandbox.bolt.com",
                            "resource": "default.png"
                        }
                    },
                    "visibility": "merchant"
                },
                {
                    "date": 1566923343433,
                    "type": "note",
                    "note": "Created Order",
                    "consumer": {
                        "id": "CAi8cQ5u5vL5P",
                        "first_name": "Bolt",
                        "last_name": "Team",
                        "avatar": {
                            "domain": "img-sandbox.bolt.com",
                            "resource": "default.png"
                        }
                    },
                    "visibility": "merchant"
                }
            ],
            "refunded_amount": {
                "amount": 0,
                "currency": "USD",
                "currency_symbol": "$"
            },
            "refund_transaction_ids": [],
            "refund_transactions": [],
            "source_transaction": null,
            "adjust_transactions": []
        }
TRANSACTION;
        $transaction = json_decode($transaction);
        return $transaction;
    }
}
