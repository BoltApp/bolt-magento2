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
 * @copyright  Copyright (c) 2017-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Plugin\Block;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Plugin\Block\SectionConfigPlugin
 */
class SectionConfigPluginTest extends TestCase
{

    /**
     * @test
     * that afterGetSections appends boltcart to the list of invalidated sections of every action
     * that currently invalidates the 'cart' section
     *
     * @covers ::afterGetSections
     *
     * @dataProvider afterGetSections_withVariousSectionsConfigProvider
     *
     * @param array $sections original sections before plugin call
     * @param array $expectedResult after the plugin
     */
    public function afterGetSections_withVariousSectionsConfig_appends($sections, $expectedResult)
    {
        $subject = $this->createMock(\Magento\Customer\Block\SectionConfig::class);
        static::assertEquals($expectedResult, (new SectionConfigPlugin())->afterGetSections($subject, $sections));
    }

    /**
     * Data provider for {@see afterGetSections_withVariousSectionsConfig_appends}
     *
     * @return array containing original sections and sections expected after the plugin
     */
    public function afterGetSections_withVariousSectionsConfigProvider()
    {
        return [
            [
                'sections'       => [],
                'expectedResult' => []
            ],
            [
                'sections'       => [
                    'thirdparty/section/enteredmanually' => [
                        'boltcart'
                    ]
                ],
                'expectedResult' => [
                    'thirdparty/section/enteredmanually' => [
                        'boltcart'
                    ]
                ]
            ],
            [
                'sections'       => [
                    'stores/store/switch'                             => '*',
                    'stores/store/switchrequest'                      => '*',
                    'directory/currency/switch'                       => '*',
                    '*'                                               => [
                        'messages',
                    ],
                    'customer/account/logout'                         => [
                        'recently_viewed_product',
                        'recently_compared_product',
                    ],
                    'customer/account/loginpost'                      => '*',
                    'customer/account/createpost'                     => '*',
                    'customer/account/editpost'                       => '*',
                    'customer/ajax/login'                             => [
                        'checkout-data',
                        'cart',
                        'captcha',
                    ],
                    'catalog/product_compare/add'                     => [
                        'compare-products',
                    ],
                    'catalog/product_compare/remove'                  => [
                        'compare-products',
                    ],
                    'catalog/product_compare/clear'                   => [
                        'compare-products',
                    ],
                    'sales/guest/reorder'                             => [
                        'cart',
                    ],
                    'sales/order/reorder'                             => [
                        'cart',
                    ],
                    'checkout/cart/add'                               => [
                        'cart',
                    ],
                    'checkout/cart/delete'                            => [
                        'cart',
                    ],
                    'checkout/cart/updatepost'                        => [
                        'cart',
                    ],
                    'checkout/cart/updateitemoptions'                 => [
                        'cart',
                    ],
                    'checkout/cart/couponpost'                        => [
                        'cart',
                    ],
                    'checkout/cart/estimatepost'                      => [
                        'cart',
                    ],
                    'checkout/cart/estimateupdatepost'                => [
                        'cart',
                    ],
                    'checkout/onepage/saveorder'                      => [
                        'cart',
                        'checkout-data',
                        'last-ordered-items',
                        'bolthints',
                        'checkout-fields',
                    ],
                    'checkout/sidebar/removeitem'                     => [
                        'cart',
                    ],
                    'checkout/sidebar/updateitemqty'                  => [
                        'cart',
                    ],
                    'rest/*/v1/carts/*/payment-information'           => [
                        'cart',
                        'checkout-data',
                        'last-ordered-items',
                        'instant-purchase',
                    ],
                    'rest/*/v1/guest-carts/*/payment-information'     => [
                        'cart',
                    ],
                    'rest/*/v1/guest-carts/*/selected-payment-method' => [
                        'cart',
                        'checkout-data',
                    ],
                    'rest/*/v1/carts/*/selected-payment-method'       => [
                        'cart',
                        'checkout-data',
                        'instant-purchase',
                    ],
                    'customer/address/*'                              => [
                        'instant-purchase',
                    ],
                    'customer/account/*'                              => [
                        'instant-purchase',
                    ],
                    'vault/cards/deleteaction'                        => [
                        'instant-purchase',
                    ],
                    'multishipping/checkout/overviewpost'             => [
                        'cart',
                    ],
                    'authorizenet/directpost_payment/place'           => [
                        'cart',
                        'checkout-data',
                    ],
                    'paypal/express/placeorder'                       => [
                        'cart',
                        'checkout-data',
                    ],
                    'paypal/payflowexpress/placeorder'                => [
                        'cart',
                        'checkout-data',
                    ],
                    'paypal/express/onauthorization'                  => [
                        'cart',
                        'checkout-data',
                    ],
                    'persistent/index/unsetcookie'                    => [
                        'persistent',
                    ],
                    'review/product/post'                             => [
                        'review',
                    ],
                    'braintree/paypal/placeorder'                     => [
                        'cart',
                        'checkout-data',
                    ],
                    'wishlist/index/add'                              => [
                        'wishlist',
                    ],
                    'wishlist/index/remove'                           => [
                        'wishlist',
                    ],
                    'wishlist/index/updateitemoptions'                => [
                        'wishlist',
                    ],
                    'wishlist/index/update'                           => [
                        'wishlist',
                    ],
                    'wishlist/index/cart'                             => [
                        'wishlist',
                        'cart',
                    ],
                    'wishlist/index/fromcart'                         => [
                        'wishlist',
                        'cart',
                    ],
                    'wishlist/index/allcart'                          => [
                        'wishlist',
                        'cart',
                    ],
                    'wishlist/shared/allcart'                         => [
                        'wishlist',
                        'cart',
                    ],
                    'wishlist/shared/cart'                            => [
                        'cart',
                    ],
                    'awgiftcard/cart/apply'                           => [
                        'cart',
                    ],
                    'awgiftcard/cart/remove'                          => [
                        'cart',
                    ],
                ],
                'expectedResult' => [
                    'stores/store/switch'                             => '*',
                    'stores/store/switchrequest'                      => '*',
                    'directory/currency/switch'                       => '*',
                    '*'                                               => [
                        'messages',
                    ],
                    'customer/account/logout'                         => [
                        'recently_viewed_product',
                        'recently_compared_product',
                    ],
                    'customer/account/loginpost'                      => '*',
                    'customer/account/createpost'                     => '*',
                    'customer/account/editpost'                       => '*',
                    'customer/ajax/login'                             => [
                        'checkout-data',
                        'cart',
                        'captcha',
                        'boltcart',
                    ],
                    'catalog/product_compare/add'                     => [
                        'compare-products',
                    ],
                    'catalog/product_compare/remove'                  => [
                        'compare-products',
                    ],
                    'catalog/product_compare/clear'                   => [
                        'compare-products',
                    ],
                    'sales/guest/reorder'                             => [
                        'cart',
                        'boltcart',
                    ],
                    'sales/order/reorder'                             => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/add'                               => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/delete'                            => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/updatepost'                        => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/updateitemoptions'                 => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/couponpost'                        => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/estimatepost'                      => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/cart/estimateupdatepost'                => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/onepage/saveorder'                      => [
                        'cart',
                        'checkout-data',
                        'last-ordered-items',
                        'bolthints',
                        'checkout-fields',
                        'boltcart',
                    ],
                    'checkout/sidebar/removeitem'                     => [
                        'cart',
                        'boltcart',
                    ],
                    'checkout/sidebar/updateitemqty'                  => [
                        'cart',
                        'boltcart',
                    ],
                    'rest/*/v1/carts/*/payment-information'           => [
                        'cart',
                        'checkout-data',
                        'last-ordered-items',
                        'instant-purchase',
                        'boltcart',
                    ],
                    'rest/*/v1/guest-carts/*/payment-information'     => [
                        'cart',
                        'boltcart',
                    ],
                    'rest/*/v1/guest-carts/*/selected-payment-method' => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'rest/*/v1/carts/*/selected-payment-method'       => [
                        'cart',
                        'checkout-data',
                        'instant-purchase',
                        'boltcart',
                    ],
                    'customer/address/*'                              => [
                        'instant-purchase',
                    ],
                    'customer/account/*'                              => [
                        'instant-purchase',
                    ],
                    'vault/cards/deleteaction'                        => [
                        'instant-purchase',
                    ],
                    'multishipping/checkout/overviewpost'             => [
                        'cart',
                        'boltcart',
                    ],
                    'authorizenet/directpost_payment/place'           => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'paypal/express/placeorder'                       => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'paypal/payflowexpress/placeorder'                => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'paypal/express/onauthorization'                  => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'persistent/index/unsetcookie'                    => [
                        'persistent',
                    ],
                    'review/product/post'                             => [
                        'review',
                    ],
                    'braintree/paypal/placeorder'                     => [
                        'cart',
                        'checkout-data',
                        'boltcart',
                    ],
                    'wishlist/index/add'                              => [
                        'wishlist',
                    ],
                    'wishlist/index/remove'                           => [
                        'wishlist',
                    ],
                    'wishlist/index/updateitemoptions'                => [
                        'wishlist',
                    ],
                    'wishlist/index/update'                           => [
                        'wishlist',
                    ],
                    'wishlist/index/cart'                             => [
                        'wishlist',
                        'cart',
                        'boltcart',
                    ],
                    'wishlist/index/fromcart'                         => [
                        'wishlist',
                        'cart',
                        'boltcart',
                    ],
                    'wishlist/index/allcart'                          => [
                        'wishlist',
                        'cart',
                        'boltcart',
                    ],
                    'wishlist/shared/allcart'                         => [
                        'wishlist',
                        'cart',
                        'boltcart',
                    ],
                    'wishlist/shared/cart'                            => [
                        'cart',
                        'boltcart',
                    ],
                    'awgiftcard/cart/apply'                           => [
                        'cart',
                        'boltcart',
                    ],
                    'awgiftcard/cart/remove'                          => [
                        'cart',
                        'boltcart',
                    ],
                ]
            ],
        ];
    }
}

