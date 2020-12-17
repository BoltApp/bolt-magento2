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

namespace Bolt\Boltpay\Model\Api;

use Bolt\Boltpay\Api\WebhookInterface;
use Bolt\Boltpay\Api\DiscountCodeValidationInterface;

class Webhook implements WebhookInterface
{
    /**
     * @var DiscountCodeValidationInterface
     */
    protected $discountCodeValidation;

    public function __construct(
        DiscountCodeValidationInterface $discountCodeValidation
    )
    {
        $this->discountCodeValidation = $discountCodeValidation;
    }

    public function execute(
        $type = null,
        $data = null
    )
    {
        try {
            switch($type){
                case "create_order":
                break;
                case "manage_order":
                break;
                case "validate_discount":
                    $this->discountCodeValidation->validate($data);
                break;
                case "cart_update":
                break;
                case "shipping_methods":
                break;
                case "shipping_options":
                break;
                case "tax":
                break;
                
            }

        }
        catch (\Exception $e) {

        }

        return true;
    }
}