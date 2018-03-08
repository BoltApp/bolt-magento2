This is a guide to installing the Bolt Payment Plugin to a Magento 2 store.

[block:callout]
{
  "type": "info",
  "title": "Supported Magento 2 Versions",
  "body": "* **2.2.x**"
}
[/block]

[block:api-header]
{
  "title": "1. Requirements"
}
[/block]

+ **Magento 2.2.0 or greater**
+ **Composer PHP Dependency Manager**

[block:api-header]
{
  "title": "2. Plugin installation"
}
[/block]

+ Download and unpack or clone the source code: https://github.com/BoltApp/bolt_integrations/tree/master/magento2
+ Upload the `Bolt` directory to your `<MAGENTO_ROOT>/app/code` directory
+ Open command prompt, go to `<MAGENTO_ROOT>` folder and run the following
commands:

[block:code]
{
  "codes": [
    {
      "code": "$ composer require \"bugsnag/bugsnag:^3.0\"\n$ php bin/magento setup:upgrade\n$ php bin/magento setup:static-content:deploy\n$ php bin/magento cache:clean",
      "language": "shell"
    }
  ]
}
[/block]

[block:api-header]
{
  "title": "3. Plugin configuration"
}
[/block]

Login to the store admin panel.
Navigate to `Stores` ? `Configuration` ? `Sales` ? `Payment Methods` ? `Bolt Pay`.
The essential settings are described below.

+ `Enabled` dropdown enables / disables the Bolt Payment method.
Select ***Yes*** to enable it.
+ Enter an appropriate `Title` such as ***Credit & Debit Cards***

[block:callout]
{
  "type": "info",
  "body": "The following four required values, (i.e. `API Key`, `Signing Secret`, `Publishable Key - Multi Step`, and `Publishable Key - Payment Only` can be found in your ***Bolt Merchant Dashboard***  under `Settings` ? `Users and Keys`\n\nFor production, these will be found at:\nhttps://merchant.bolt.com\n\nFor sandbox mode, use the following URL:\nhttps://merchant-sandbox.bolt.com",
  "title": "API credentials"
}
[/block]

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
+ **Automatic Capture Mode**  
capturing funds configuration

[block:callout]
{
  "type": "info",
  "body": "**YES** - both authorization and capture are done in a single step\n**NO** - the funds are captured in a separate request, initiated either from the store admin panel or from the Bolt merchant dashboard"
}
[/block]

[block:api-header]
{
  "title": "4. Bolt Merchant Dashboard configuration"
}
[/block]

[block:callout]
{
  "type": "info",
  "body": "**Production**: https://merchant.bolt.com\n\n**Sandbox**: https://merchant-sandbox.bolt.com",
  "title": "Login to the Bolt Merchant Dashboard"
}
[/block]
+ Navigate to `Settings` ? `Keys and URLs`
+ Scroll down to the `URL Configurations` section
+ Set **Webhook** URL to: `[store_url]/rest/V1/bolt/boltpay/order/manage`  
+ Set **Shipping and Tax** URL to: `[store_url]/rest/V1/bolt/boltpay/shipping/methods`  

# Success!  
Your Bolt Payment Plugin is now installed and configured.