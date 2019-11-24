import http from "k6/http";
import { URL, PREAUTH_PATH, create_header } from "../config.js";
import { create_preauth_payload } from "../inputs/preauth_payload.js";

export function create_order( order_token, order_reference ) {
    const create_order_url =  URL + PREAUTH_PATH;
    const data =  create_preauth_payload( order_token, order_reference );
    const header = create_header( data );
    return http.post( create_order_url, JSON.stringify( data ), { headers: header } );
}