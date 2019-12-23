import { cart_data } from "./rpcs/cartdata.js";
import { shipping_and_tax } from "./rpcs/shippingtax.js";
import { create_order } from "./rpcs/create_order.js";
import { check } from "k6";
import { Rate } from "k6/metrics";

const errorRate = new Rate( "errors" );

export default function test() {
    // cart page
    const cart_page_response = cart_data();
    check( cart_page_response, { status_200: r => r.status == 200 } ) || errorRate.add(1);
    if ( cart_page_response.status == 200 ) {
        const json_body = JSON.parse( cart_page_response.body );
        const order_token = json_body.order_token;
        const order_reference = json_body.order_reference;

        // shipping and tax
        const shipping_response = shipping_and_tax( order_token, order_reference );
        check( shipping_response, { status_200: r => r.status == 200 } ) || errorRate.add(1);

        // pre-auth
        const preauth_response = create_order( order_token, order_reference );
        check( preauth_response, { status_200: r => r.status == 200 } ) || errorRate.add(1);
    }
    else {
        console.log("Error on cart_data request: " + cart_page_response.body);
    }
}