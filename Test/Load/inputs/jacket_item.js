import { create_amount } from "./amount.js";
import { URL } from "../config.js";

export function create_jacket_item ( is_pre_auth ) {
    return {
        "reference": "408",
        "name": "Montana Wind Jacket-S-Green",
        "description": "Light-as-a-feather wind protection for runners, walkers and outdoor fitness buffs," +
            " the Montana Wind Jacket can be stuffed into your pocket for portable protection." +
            " Its stylish, move-with-you design makes it especially versatile." +
            "\\nAdjustable hood. Split pocket. Thumb holes. Machine wash/hang to dry.",
        "options": null,
        "total_amount": is_pre_auth ? create_amount(4900) : 4900,
        "unit_price": is_pre_auth ? create_amount(4900) : 4900,
        "tax_amount": is_pre_auth ? create_amount(0) : 0,
        "quantity": 1,
        "uom": null,
        "upc": null,
        "sku": "MJ03-S-Green",
        "isbn": null,
        "brand": null,
        "manufacturer": null,
        "category": null,
        "tags": null,
        "properties": [],
        "color": null,
        "size": null,
        "weight": null,
        "weight_unit": null,
        "image_url": URL + "/pub/media/catalog/product/cache/a2d2345650965cd6042e53fd7d716674/m/j/mj03-green_main.jpg",
        "details_url": null,
        "taxable": true,
        "tax_code": null,
        "type": "physical"
    };

}