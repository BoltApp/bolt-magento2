import { address } from "./address.js";
import { create_shippingtax_cart } from "./shippingtax_cart.js";

export function create_shipping_info( order_token, order_reference ) {
    return {
        "order_token": order_token,
        "cart": create_shippingtax_cart( order_reference, false ),
        "shipping_address": address,
        "shipping_options": [],
        "request_source": "checkout"
    };
}