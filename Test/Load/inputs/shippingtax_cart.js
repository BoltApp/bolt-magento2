import { address } from "./address.js";
import { create_jacket_item } from "./jacket_item.js";

export function create_shippingtax_cart ( order_reference ) {
    return {
        "order_reference": order_reference,
        "display_id": "000000316 / " +   order_reference,
        "currency": "USD",
        "total_amount": 4900,
        "tax_amount": 0,
        "billing_address": address,
        "billing_address_id": null,
        "items": [
            create_jacket_item( false ),
        ],
        "shipments": null,
        "discounts": 0,
        "discount_code": "",
        "order_description": null,
        "transaction_reference": null,
        "cart_url": null,
        "is_shopify_hosted_checkout": false
    };
}