<?php
use Magento\Framework\App\Bootstrap;

/**
 * If your external file is in root folder
 */
require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');


$coupon['name'] = 'freeshipping for fixed price shipping option';
$coupon['desc'] = 'freeshipping for fixed price shipping option';
$coupon['start'] = date('Y-m-d');
$coupon['end'] = '';
$coupon['max_redemptions'] = 1;
$coupon['discount_type'] ='by_percent';
$coupon['discount_amount'] = 0;
$coupon['flag_is_free_shipping'] = 'matching_items';
$coupon['redemptions'] = 1;
$coupon['code'] ='freeshippingfixed'; //this code will normally be autogenetated but i am hard coding for testing purposes

$shoppingCartPriceRule = $obj->create('Magento\SalesRule\Model\Rule');
$shoppingCartPriceRule->setName($coupon['name'])
        ->setDescription($coupon['desc'])
        ->setFromDate($coupon['start'])
        ->setToDate($coupon['end'])
        ->setUsesPerCustomer($coupon['max_redemptions'])
        ->setCustomerGroupIds(['0','1','2','3',])
        ->setIsActive(1)
        ->setSimpleAction($coupon['discount_type'])
        ->setDiscountAmount($coupon['discount_amount'])
        ->setDiscountQty(1)
        ->setApplyToShipping($coupon['flag_is_free_shipping'])
        ->setTimesUsed($coupon['redemptions'])
        ->setWebsiteIds(['1'])
        ->setCouponType(2)
        ->setCouponCode($coupon['code'])
        ->setUsesPerCoupon(null)
        ->setSimpleFreeShipping(1);
$shoppingCartPriceRule->save();
