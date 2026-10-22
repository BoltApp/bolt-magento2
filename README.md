## Bolt Checkout Plugin for Magento 2 

[![Latest Stable Version](https://poser.pugx.org/boltpay/bolt-magento2/v/stable.png)](https://packagist.org/packages/boltpay/bolt-magento2)
[![Build Status](https://circleci.com/gh/BoltApp/bolt-magento2.svg?style=shield)](https://circleci.com/gh/BoltApp/bolt-magento2)

### 1. Requirements

+ **Magento 2.3.0 or greater**
+ **Composer PHP Dependency Manager**

> #### PHP security update (October 2026)
> [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
> and [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)
> are critical remote code execution vulnerabilities in PHP itself. An attacker does not need
> to log in to exploit them. The bugs are not in Magento or in this plugin, so no plugin
> update can fix them. **Upgrading PHP is the only complete fix.**
>
> The patched releases are 8.2.35, 8.3.36, 8.4.27 and 8.5.12. PHP 8.1 and earlier will not
> get a fix, so a store on Magento 2.4.5 or older has to move to Magento 2.4.6 or later before
> it can run a patched PHP:
>
> | Magento | Patched PHP versions it supports |
> |---|---|
> | 2.4.6 | 8.2.35 or later |
> | 2.4.7 | 8.2.35 or later, 8.3.36 or later |
> | 2.4.8 | 8.3.36 or later, 8.4.27 or later |
> | 2.4.9 | 8.5.12 or later |
>
> Until you can upgrade, set `session.upload_progress.enabled = 0` in `php.ini`. Magento does
> not use PHP's upload progress tracking. On PHP 8.5, also set `fatal_error_backtraces = 0`.
> Then restart every PHP worker, and check that no FPM pool, `.htaccess` or `.user.ini`
> setting turns either option back on. These settings reduce the risk but do not remove it.
> The two advisories linked above describe further mitigations.

### 2. Plugin installation

+ Open command prompt, go to `<MAGENTO_ROOT>` folder and run the following
commands:

```
$ composer require boltpay/bolt-magento2
$ php bin/magento setup:upgrade
$ php bin/magento setup:di:compile
$ php bin/magento setup:static-content:deploy
$ php bin/magento cache:clean
$ php bin/magento cache:flush
```

### 3. Plugin configuration

Login to the store admin panel.
Navigate to `Stores` > `Configuration` > `Sales` > `Payment Methods` > `Bolt Pay`.
The essential settings are described below.

+ `Enabled` dropdown enables / disables the Bolt Payment method.
Select ***Yes*** to enable it.
+ Enter an appropriate `Title` such as ***Credit & Debit Cards***

> #### API credentials
> The following three required values, (i.e. `API Key`, `Signing Secret`, `Publishable Key`) can be found in your ***Bolt Merchant Dashboard***  under `Developers` > `API`
> The Publishable Key is for the multi-step checkout by default. You can find other publishable keys (Payment Only and Back-Office) in the division dropdown at the top-right corner.

> For production, these will be found at:
> https://merchant.boltapp.com
>
> For sandbox mode, use the following URL:
> https://merchant-sandbox.boltapp.com"


+ **API Key**
used for calling Bolt API from your back-end server
+ **Signing Secret**
used for signature verification in checking the authenticity of webhook requests
+ **Publishable Key - Multi Step**
used to open the Bolt Payment Popup typically on Shopping cart and product pages
+ **Publishable Key - Payment Only**
used to open the Bolt Payment Popup typically on checkout pages
+ **Publishable Key - Back Office**
used to open the Bolt Payment Popup typically on the admin order creation page 
+ **Sandbox Mode**
setting up testing vs. production execution environment
+ **Replace Button Selectors**
comma separated list of CSS selectors matching the elements to be replaced with Bolt checkout buttons, or Bolt checkout buttons placed alongside them
>> `no suffix` - the default, inserts the Bolt button in place of the element and removes the element
>>
>> `|append` suffix - *example-selector|append*, inserts Bolt button right after the element
>>
>> `|prepend` suffix - *example-selector|prepend*, inserts Bolt button right before the element
### 4. Bolt Merchant Dashboard configuration
> #### Login to the Bolt Merchant Dashboard
> **Production**: https://merchant.boltapp.com
>
> **Sandbox**: https://merchant-sandbox.boltapp.com"

+ Navigate to `Developers`
+ Click on the `API` tab
+ Scroll down to the `Merchant API` section
+ Set **Webhook** URL to: `[store_url]/rest/V1/bolt/boltpay/order/manage`
+ Set **Shipping and Tax** URL to: `[store_url]/rest/V1/bolt/boltpay/shipping/methods`
+ Set **Create Order** URL to: `[store_url]/rest/V1/bolt/boltpay/order/create`

# Success!
Your Bolt Payment Plugin is now installed and configured.
