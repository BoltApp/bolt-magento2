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
