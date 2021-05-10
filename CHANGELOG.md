# Changelog
## [v1.0.4] 2018-06-19
## [v1.0.5] 2018-08-21
## [v1.0.6] 2018-08-23
## [v1.0.7] 2018-09-07
## [v1.0.8] 2018-09-16
## [v1.0.9] 2018-09-19
## [v1.0.10] 2018-09-23
## [v1.0.11] 2018-10-01
## [v1.0.12] 2018-10-10
## [v1.1.0] 2018-10-11
## [v1.1.1] 2018-10-23
## [v1.1.2] 2018-10-30
## [v1.1.3] 2018-11-27
## [v1.1.4] 2018-12-04
## [v1.1.5](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.5) 2018-12-11
 - Use circleCI instead of TravisCI
 - Prevent order ceation API call with an empty cart
 - Complete order stays in payment review state on a long hook delay fix
 - Invalid capture amount failed hook fix
## [v1.1.6](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.6) 2018-12-13
 - Force approve/reject failed hook fix
## [v1.1.7](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.7) 2018-12-21
 - Amasty Gift Card support
 - No status after unhold fix
## [v1.1.8](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.8) 2019-01-09
 - Check if order payment method is 'boltpay'
 - Add currency_code field to cart currency data
 - Dispatch sales_quote_save_after event for active (parent) quotes only
 - Fixed consistency for Amasty Gift Card module
## [v1.1.9](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.9) 2019-01-24
 - Allow empty emails in shipping_and_tax API
 - Add feature to optionally not inject JS on non-checkout pages
 - Sent store order notifications to email collected from Bolt checkout
 - Create order from parent quote
 - Do not cache empty shipping options array
## [v1.1.10](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.10) 2019-02-11
 - Add support for item properties
 - Tax mismatch adjustment
 - Unirgy_Giftcert plugin support
 - Remove active quote restriction on order creation (backend order fix)
 - Reserve Order ID for the child quote, defer setting parent quote order ID until just before quote to order submission
## [v1.1.11](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.11) 2019-03-07
 - Backoffice hook no pending fix
 - Checkout initialization fix
 - Restrict plugin availability in regards to client IP address (white list)
 - Shipping and tax cart validation update - support for multiple items with the same SKU
 - Email field mandatory for back-office orders
 - Prevent setting empty hint prefill field
 - Cart data currency error fix
 - Back office order creation fix
 - Create invoice for zero amount order
 - Exclude empty address fields from save when creating the order
 - Store Credit on Shopping Cart support
 - Update populating the checkout address from hints prefill
## [v1.1.12](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.12) 2019-04-01
 - Allow Admin to update order manually
 - Back-office create order check for shipping method
 - Multi-store support
 - One step checkout support / disable bolt on payment only checkout pages
 - Add config for merchant to specify EmailEnter callback
 - Update ajax order creation error message
## [v1.1.12.1](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.12.1) 2019-04-10
 - Fix to support multi-stores with no default api key
## [v1.1.13](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.13) 2019-04-26
 - Various bug fixes
## [v1.1.14](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.14) 2019-05-28
 - Various bug fixes
## [v1.1.15](https://github.com/BoltApp/bolt-magento2/releases/tag/1.1.15) 2019-06-12
 - Fixes for multi-store backend support.
 - Option to toggle emulated session in api calls
## [v2.0.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.0.0) 2019-07-02
 - Introducing pre-authorized order creation
## [v2.0.1](https://github.com/BoltApp/bolt-magento2/releases/tag/2.0.1) 2019-09-06
 - Added generic ERP support
 - Removed Autocapture from settings
## [v2.0.2](https://github.com/BoltApp/bolt-magento2/releases/tag/2.0.2) 2019-09-12
 - Support for Paypal
## [v2.0.3](https://github.com/BoltApp/bolt-magento2/releases/tag/2.0.3) 2019-10-28
 - Testing and logging fixes
 - Beta merchant metrics feature
 - Various bug fixes
## [v2.1.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.1.0) 2019-11-21
 - Paypal payment support
 - [Beta] Feature switches
   - graphQL client for Bolt server communication
 - BSS store credit support
 - Improved checkout metricing
 - Various bug fixes
## [v2.2.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.2.0) 2020-02-05
 - [Beta] Simple Product Page Checkout
 - Staged Rollout
 - Some M2 2.3.4 compat. fixes
 - Multicurrency improvements
 - Various bug fixes
## [v2.3.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.3.0) 2020-02-20
 - Custom checkboxes
 - Re-order feature for logged-in customers
 - Product page checkout improvements
 - Various bug fixes
## [v2.4.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.4.0) 2020-03-11
 - Bug fixes
 - Added JS event for when hints are set
## [v2.4.1](https://github.com/BoltApp/bolt-magento2/releases/tag/2.4.1) 2020-03-18
 - Fix Bolt checkout not opening on IE
## [v2.5.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.5.0) 2020-03-27
 - Add support for boltPrimaryActionColor
 - Moved some CSS to M2 config page
 - Custom options support for simple products in product page checkout
 - Webhook log cleaner cron
 - Improved api result caching
 - Improved debug webhook data collection
 - Bug fixes
## [v2.6.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.6.0) 2020-05-04
 - Debug webhook now fully available
 - In-store pickup feature
 - Pay-by-link added
 - Unit tests and code cleanup
 - Admin page reoganization
 - Development quality of life fixes
 - Support for shipping discounts
 - Add Bolt account button for order management
 - Added Amasty store credit support
## [v2.7.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.7.0) 2020-05-12
 - Add catalog price rule support for backoffice
 - Unit tests
 - Bug fixes
## [v2.8.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.8.0) 2020-05-28
 - Splitting shipping and tax endpoints
 - Add always-present Bolt checkout button
 - Added custom url validation
 - Bug fixes
 - Unit tests
## [v2.8.1](https://github.com/BoltApp/bolt-magento2/releases/tag/2.8.1) 2020-06-11
 - Fix PPC javascript error in Magento 2 version 2.3.5
 - Fix unknown RevertGiftCardAccountBalance error
## [v2.9.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.9.0) 2020-06-17
 - Fix display of APM/Paypal transactions within Magento 2 dashboard
 - Always-present checkout button improvements
 - Update to method to save Bolt cart in to be more robust
 - Added support for tracking non-Bolt order shipments.
 - Code maintainability refactoring
 - Bug fixes
## [v2.10.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.10.0) 2020-07-20
 - Fixes for latency regressions introduced in 2.9.0
 - Refactoring to optimize number of calls made on page loading
 - Customization branch restructuring
 - Bug fixes
 ## [v2.11.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.11.0) 2020-07-29
 - Improve support for bolt order management (beta)
 - Bug fixes
 ## [v2.12.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.12.0) 2020-08-12
 - Add support for Magento Commerce 2.4
 - Improve support for bolt order management (beta)
 - Add support for plugin Amasty giftcard 2.0.0
 - Support for gift wrapping info
 - Bug fixes
 ## [v2.13.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.13.0) 2020-08-19
 - Improved back-end components related to checkout experience.
 - Support for the Mageplaza plugin's shipping restriction
 ## [v2.14.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.14.0) 2020-09-15
 - Added: Shoppers can now add multiple discounts and remove discounts in Bolt Checkout (Magento discounts only).
 - Improvement: The `display_id` now displays just the `order_id` value in the merchant dashboard and user emails.
 ## [v2.15.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.15.0) 2020-10-05
 - Improvement: Product Page Checkout now supports cart item grouping (itemGroup).
 - Improvement: Bolt now clears the cached shipping and tax information when the shipping method is changed.
 - Fixed: Resolved compatibility issues with MageWorld Reward Points Pro
 ## [v2.16.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.16.0) 2020-10-19
 - Fixed: Resolved issue where shoppers were unable to apply Aheadworks Store Credit to their cart.
 - Fixed: Resolved issues with Amasty Gift Cards being applied to orders placed in the back-office and storefront.  
 - Added: The M2 Plugin now supports product addons (removing and adding suggested items to checkout).
 ## [v2.17.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.17.0) 2020-11-03
 - Fixed: Resolved issue where discounts applied to an order placed from the M2 Admin Console did not apply in Bolt Checkout Modal.
 - Improvement: Refunds for Paypal transactions now support an `in-progress` status for situations where the merchant does not yet have sufficient funds.
 - Improvement: Made general improvements related to Mirasvit rewards points usage such as tax calculations and shipping discounts.
 - Added: Merchants can now selectively configure Product Page Checkout to display only for specific products that have the `bolt_ppc` attribute.

## [v2.18.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.18.0) 2021-01-06
- Added: Merchants can now recognize Apple Pay orders from their Payment Information as `Bolt-Applepay` in the Magento Admin console.
- Improvement: We added an optional feature switch that updates orders with failed payment hooks to a `canceled` status instead of deleting them. This can be useful for merchants that use ERP systems. For activation, reach out to your customer success manager.   
- Improvement: Now merchants can see the cart type and last four digits when reviewing orders from all processors.
- Improvement: Discounts got a small refresh in the way their information is displayed.
- Improvement: We did some refactoring for our payment-only checkout flow.
- Fixed: There was a very unlikely (but still possible) chance that changes to Mirsavit credit applied to the cart did not update, so we made sure it will update every time.
## [v2.18.1](https://github.com/BoltApp/bolt-magento2/releases/tag/2.18.1) 2021-01-21
- Improvement: The order grid in the Magento admin console now prioritizes displaying credit card details over payment processor information.
- Fixed: Resolved issue where the order grid in the Magento admin console would freeze when no order records matched the user's defined filtering criteria.
## [v2.19.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.19.0) 2021-02-10
- The M2 plugin now supports the default **Edit Order** functionality in Magento Admin. This enables merchants to edit orders from the Magento Admin Console.
## [v2.20.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.20.0) 2021-03-08
- Product addons are now supported for M2.
- The Universal API is now supported for M2.
- Custom fields (dropdowns, checkboxes) have been refactored for better performance in the future.
- Resolved issue with tax calculations where fixed discounts on the whole cart caused the final calculation to throw the error `cart tax mismatched`.
- Resolved issue where the mini cart occasionally displayed items after a shopper has checked out and is on the order success page.
## [v2.21.0](https://github.com/BoltApp/bolt-magento2/releases/tag/2.21.0) 2021-04-08
- This plugin now supports Zonos custom shipping.
- This plugin now supports Mageside's Custom Shipping Price module.
- We’ve optimized the way Bolt checkout handles store credit and rewards points.
- We’ve improved how shopping sessions with cart persistence are handled when this Magento feature is enabled. 
- Resolved issue where shoppers were unable to apply free shipping coupons during checkout.
- Resolved issue where shoppers were unable to purchase digital products where selecting a product option was required (for example, an ebook where selecting “Special Edition” or “Standard” is required).
## [v2.21.1](https://github.com/BoltApp/bolt-magento2/releases/tag/2.21.1) 2021-05-04
- Include account.bolt.com in M2 Content Security Policy allow-list
- Fix a bug with order associations for Bolt SSO
## [v2.21.2](https://github.com/BoltApp/bolt-magento2/releases/tag/2.21.2)
- Fix bug with backoffice orders
- Fix a regression with handling custome fields
