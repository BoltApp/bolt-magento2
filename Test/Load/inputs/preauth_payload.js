import { create_preauth_cart } from "./preauth_cart.js";

export function create_preauth_payload(order_token, order_reference) {
    return {
        "type": "order.create",
        "order": {
          "token": order_token,
          "cart": create_preauth_cart( order_reference )
        }
    };
}