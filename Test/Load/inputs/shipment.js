import { address } from "./address.js";
import { create_amount } from "./amount.js";

export const shipment = {
    "shipping_address": address,
    "shipping_method": "unknown",
    "service": "Flat Rate - Fixed",
    "cost": create_amount(500),
    "tax_amount": create_amount(0),
    "reference": "flatrate_flatrate"
};