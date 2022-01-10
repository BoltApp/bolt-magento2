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

namespace Bolt\Boltpay\Test\Unit\ThirdPartyModules\Magecomp;

use Bolt\Boltpay\ThirdPartyModules\Magecomp\Extrafee;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\MockObject\MockObject;
use Bolt\Boltpay\Test\Unit\BoltTestCase;
use Magento\Quote\Model\Quote;

/**
 * @coversDefaultClass \Bolt\Boltpay\ThirdPartyModules\Magecomp\Extrafee
 */
class ExtrafeeTest extends BoltTestCase
{
    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var Extrafee|MockObject
     */
    private $currentMock;

    /**
     * @var Total|MockObject
     */
    private $totalMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUpInternal()
    {
        $this->currentMock = $this->getMockBuilder(Extrafee::class)->setMethods()->getMock();
        $this->quoteMock = $this->createPartialMock(Quote::class, ['getTotals', 'getData', 'getQuoteCurrencyCode']);
        $this->totalMock = $this->createPartialMock(Total::class, ['getTitle']);
    }

    /**
     * @test
     * that filterCartItems adds a dummy product with matching totals if Extrafee is present in totals
     *
     * @covers ::filterCartItems
     */
    public function filterCartItems_ifExtraFeeInQuoteTotals_addsDummyItemToProductsMatchingTheTotal()
    {
        $this->quoteMock->expects(static::once())->method('getTotals')
            ->willReturn([Extrafee::EXTRA_FEE_TOTAL_CODE => $this->totalMock]);
        $fee = 123.45;
        $this->quoteMock->expects(static::once())->method('getData')->with(Extrafee::EXTRA_FEE_TOTAL_CODE)
            ->willReturn($fee);
        $this->quoteMock->expects(static::once())->method('getQuoteCurrencyCode')->willReturn('USD');
        $this->totalMock->expects(static::once())->method('getTitle')->willReturn('Extra Fee');

        list($products, $totalAmount, $diff) = $this->currentMock->filterCartItems([[], 0, 0], $this->quoteMock, 1, true);
        self::assertEquals(
            [
                [
                    'reference'    => 'third_party_fee',
                    'name'         => 'Extra Fee',
                    'total_amount' => 12345,
                    'unit_price'   => 12345,
                    'quantity'     => 1,
                ],
            ],
            $products
        );
        self::assertEquals(12345, $totalAmount);
        self::assertEquals(0, $diff);
    }

    /**
     * @test
     * that filterTransactionBeforeOrderCreateValidation removes dummy Extrafee product added by
     * @see \Bolt\Boltpay\ThirdPartyModules\Magecomp\Extrafee::filterCartItems
     *
     * @covers ::filterTransactionBeforeOrderCreateValidation
     */
    public function filterTransactionBeforeOrderCreateValidation_always_filtersOutExtraFeeDummyItem()
    {
        $regularItem = (object)([
            'reference'    => '14',
            'name'         => 'Push It Messenger Bag',
            'description'  => 'It\'s the perfect size and shape for laptop, folded clothes, even extra shoes.',
            'total_amount' => (object)[
                'amount'          => 4500,
                'currency'        => 'USD',
                'currency_symbol' => '$',
            ],
            'unit_price'   => (object)[
                'amount'          => 4500,
                'currency'        => 'USD',
                'currency_symbol' => '$',
            ],
            'quantity'     => 1,
            'sku'          => '24-WB04',
            'image_url'    => '/pub/media/catalog/product/cache/db97cdfd3ac6df8ea2b724175bc2e630/w/b/wb04-blue-0.jpg',
            'type'         => 'physical',
            'taxable'      => true,
            'properties'   => [],
        ]);
        $transaction = (object)[
            'type'     => 'order.create',
            'order'    => (object)[
                'token' => '7f3c79058d6bc38f791ae3019a87a1840bd835df602200a0c582fb44c86a80e9',
                'cart'  => (object)[
                    'order_reference' => '102',
                    'currency'        => (object)['currency' => 'USD', 'currency_symbol' => '$',],
                    'subtotal_amount' => (object)['amount' => 5000, 'currency' => 'USD', 'currency_symbol' => '$',],
                    'total_amount'    => (object)['amount' => 5500, 'currency' => 'USD', 'currency_symbol' => '$',],
                    'tax_amount'      => (object)['amount' => 0, 'currency' => 'USD', 'currency_symbol' => '$',],
                    'shipping_amount' => (object)['amount' => 500, 'currency' => 'USD', 'currency_symbol' => '$',],
                    'discount_amount' => (object)['amount' => 0, 'currency' => 'USD', 'currency_symbol' => '$',],
                    'billing_address' => (object)[
                        'id'              => 'AAc3zLJrZoTby',
                        'street_address1' => 'DO NOT SHIP',
                        'locality'        => 'Beverly Hills',
                        'region'          => 'California',
                        'postal_code'     => '90210',
                        'country_code'    => 'US',
                        'country'         => 'United States',
                        'name'            => 'DO_NOT_SHIP DO_NOT_SHIP',
                        'first_name'      => 'DO_NOT_SHIP',
                        'last_name'       => 'DO_NOT_SHIP',
                        'phone_number'    => '5305555555',
                        'email_address'   => 'bolt.integration.testing@gmail.com',
                    ],
                    'items'           => [
                        $regularItem,
                        (object)[
                            'reference'    => 'third_party_fee',
                            'name'         => 'Extra Fee',
                            'total_amount' => (object)[
                                'amount'          => 500,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                            'unit_price'   => (object)[
                                'amount'          => 500,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                            'quantity'     => 1,
                            'type'         => 'unknown',
                            'taxable'      => true,
                            'properties'   => [],
                        ],
                    ],
                    'shipments'       => [
                        0 => (object)[
                            'shipping_address' => (object)[
                                'id'              => 'AA5TofsfXmQ3v',
                                'street_address1' => 'DO NOT SHIP',
                                'locality'        => 'Beverly Hills',
                                'region'          => 'California',
                                'postal_code'     => '90210',
                                'country_code'    => 'US',
                                'country'         => 'United States',
                                'name'            => 'DO_NOT_SHIP DO_NOT_SHIP',
                                'first_name'      => 'DO_NOT_SHIP',
                                'last_name'       => 'DO_NOT_SHIP',
                                'phone_number'    => '5305555555',
                                'email_address'   => 'bolt.integration.testing@gmail.com',
                            ],
                            'shipping_method'  => 'unknown',
                            'service'          => 'Flat Rate - Fixed',
                            'cost'             => (object)[
                                'amount'          => 500,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                            'tax_amount'       => (object)[
                                'amount'          => 0,
                                'currency'        => 'USD',
                                'currency_symbol' => '$',
                            ],
                            'reference'        => 'flatrate_flatrate',
                        ],
                    ],
                    'metadata'        => (object)['immutable_quote_id' => '103',],
                ],
            ],
            'currency' => 'USD',
        ];
        $result = $this->currentMock->filterTransactionBeforeOrderCreateValidation($transaction);
        $transaction->order->cart->items = [$regularItem,];
        self::assertEquals($result, $transaction);
    }

    /**
     * @test
     * that filterCartBeforeLegacyShippingAndTax removes dummy Extrafee product added by
     * @see \Bolt\Boltpay\ThirdPartyModules\Magecomp\Extrafee::filterCartItems
     *
     * @covers ::filterCartBeforeLegacyShippingAndTax
     */
    public function filterCartBeforeLegacyShippingAndTax_always_filtersOutExtraFeeDummyItem()
    {
        $cart = [
            'total_amount'               => 9500,
            'items'                      => [
                [
                    'reference'    => '14',
                    'name'         => 'Push It Messenger Bag',
                    'description'  => 'It\'s the perfect size and shape for laptop, folded clothes, even extra shoes.',
                    'total_amount' => (object)[
                        'amount'          => 4500,
                        'currency'        => 'USD',
                        'currency_symbol' => '$',
                    ],
                    'unit_price'   => (object)[
                        'amount'          => 4500,
                        'currency'        => 'USD',
                        'currency_symbol' => '$',
                    ],
                    'quantity'     => 1,
                    'sku'          => '24-WB04',
                    'image_url'    => '/pub/media/catalog/product/cache/db97cdfd3ac6df8ea2b724175bc2e630/w/b/wb04-blue-0.jpg',
                    'type'         => 'physical',
                    'taxable'      => true,
                    'properties'   => [],
                ],
                [
                    'reference'    => 'third_party_fee',
                    'name'         => 'Extra Fee',
                    'description'  => null,
                    'options'      => null,
                    'total_amount' => 500,
                    'unit_price'   => 500,
                    'tax_amount'   => 0,
                    'quantity'     => 1,
                    'uom'          => null,
                    'upc'          => null,
                    'sku'          => null,
                    'isbn'         => null,
                    'brand'        => null,
                    'manufacturer' => null,
                    'category'     => null,
                    'tags'         => null,
                    'properties'   => [],
                    'color'        => null,
                    'size'         => null,
                    'weight'       => null,
                    'weight_unit'  => null,
                    'image_url'    => null,
                    'details_url'  => null,
                    'taxable'      => true,
                    'tax_code'     => null,
                    'type'         => 'unknown',
                ],
            ],
            'tax_amount'                 => 0,
            'billing_address_id'         => null,
            'billing_address'            => [
                'street_address1' => 'DO NOT SHIP',
                'street_address2' => '',
                'street_address3' => null,
                'street_address4' => null,
                'locality'        => 'Beverly Hills',
                'region'          => 'California',
                'postal_code'     => '90210',
                'country_code'    => 'US',
                'country'         => 'United States',
                'name'            => null,
                'first_name'      => 'DO_NOT_SHIP',
                'last_name'       => 'DO_NOT_SHIP',
                'company'         => 'DO_NOT_SHIP',
                'phone'           => '5305555555',
                'email'           => 'bolt.integration.testing@gmail.com',
            ],
            'shipments'                  => null,
            'in_store_cart_shipments'    => null,
            'discounts'                  => [],
            'discount_code'              => '',
            'discount_source'            => null,
            'currency'                   => 'USD',
            'order_description'          => null,
            'order_reference'            => '106',
            'transaction_reference'      => null,
            'cart_url'                   => null,
            'display_id'                 => null,
            'is_shopify_hosted_checkout' => false,
            'metadata'                   => ['immutable_quote_id' => '122',],
        ];
        $result = $this->currentMock->filterCartBeforeLegacyShippingAndTax($cart);
        unset($cart['items'][1]);
        static::assertEquals($cart, $result);
    }
}
