import crypto from "k6/crypto";

export const URL = "http://34.220.243.58";
export const CART_PATH = "/rest/V1/bolt/boltpay/order/cartdata";
export const SHIPPING_PATH = "/rest/V1/bolt/boltpay/shipping/methods";
export const PREAUTH_PATH = "/rest/V1/bolt/boltpay/order/create";
const SIGNING_SECRET = "76037c6eeaf7005580f4541e3e5397017e153d5f7ce97ae30656f8a830c4908f";

export function create_header( data ) {
    const hmac = crypto.hmac( 'sha256', SIGNING_SECRET, JSON.stringify( data ), 'base64' );
    return {
        "X-Bolt-Hmac-Sha256": hmac,
        "Content-Type": "application/json"
    };
}
