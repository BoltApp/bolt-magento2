## Bolt Checkout Plugin for Magento 2 
[![Latest Stable Version](https://poser.pugx.org/boltpay/bolt-magento2/v/stable.png)](https://packagist.org/packages/boltpay/bolt-magento2)
[![Build Status](https://circleci.com/gh/BoltApp/bolt-magento2.svg?style=shield)](https://circleci.com/gh/BoltApp/bolt-magento2)

### 1. Requirements

+ **Magento 2.2.0 or greater**
+ **Composer PHP Dependency Manager**

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
> The following four required values, (i.e. `API Key`, `Signing Secret`, `Publishable Key - Multi Step`, and `Publishable Key - Payment Only` can be found in your ***Bolt Merchant Dashboard***  under `Settings` > `Users and Keys`
>
> For production, these will be found at:
> https://merchant.bolt.com
>
> For sandbox mode, use the following URL:
> https://merchant-sandbox.bolt.com"


+ **API Key**
used for calling Bolt API from your back-end server
+ **Signing Secret**
used for signature verification in checking the authenticity of webhook requests
+ **Publishable Key - Multi Step**
used to open the Bolt Payment Popup typically on Shopping cart and product pages
+ **Publishable Key - Payment Only**
used to open the Bolt Payment Popup typically on checkout pages
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
> **Production**: https://merchant.bolt.com
>
> **Sandbox**: https://merchant-sandbox.bolt.com"

+ Navigate to `Settings` > `Keys and URLs`
+ Scroll down to the `URL Configurations` section
+ Set **Webhook** URL to: `[store_url]/rest/V1/bolt/boltpay/order/manage`
+ Set **Shipping and Tax** URL to: `[store_url]/rest/V1/bolt/boltpay/shipping/methods`

# Success!
Your Bolt Payment Plugin is now installed and configured.
