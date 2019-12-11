import http from "k6/http";
import { create_shipping_info } from "../inputs/shippingtax_payload.js";
import { URL, SHIPPING_PATH, create_header } from "../config.js";

export function shipping_and_tax( order_token, order_reference ) {
    const shipping_url = URL + SHIPPING_PATH;
    const data =  create_shipping_info( order_token, order_reference );
    const header = create_header( data );
    return http.post( shipping_url, JSON.stringify( data ), { headers: header });
}