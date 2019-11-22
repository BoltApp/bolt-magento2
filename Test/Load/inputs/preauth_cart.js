import { create_amount } from "./amount.js";
import { address } from "./address.js";
import { create_jacket_item } from "./jacket_item.js";
import { shipment } from "./shipment.js";

export function create_preauth_cart ( order_reference ) {
    return {
        "order_reference": order_reference,
        "display_id": "000000316 / " + order_reference,
        "currency": {
            "currency": "USD",
            "currency_symbol": "$"
        },
        "subtotal_amount": create_amount(4900),
        "total_amount": create_amount(5400),
        "tax_amount": create_amount(0),
        "shipping_amount": create_amount(500),
        "discount_amount": create_amount(0),
        "billing_address": address,
        "items": [ create_jacket_item(true) ],
        "shipments": [ shipment ],
        "discounts": []
    };
}