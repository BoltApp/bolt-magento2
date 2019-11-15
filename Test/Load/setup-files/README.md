# Load Test Setup 
1. Setup M2 store on LightSail: [M2 LightSail Setup](https://www.notion.so/boltteam/Remote-dev-server-with-lightsail-8a053570b68c4ac78561cf04cbde8405)

2. Configure your M2 store with Bolt: [M2 Bolt Configuration](https://docs.bolt.com/docs/magento-2-integration#section-2-plugin-configuration)

3. Add the line below in `bolt-magento2/etc/di.xml`: 
	```
	<preference for="Bolt\Boltpay\Api\LoadTestCartDataInterface" type="Bolt\Boltpay\Model\Api\LoadTestCartData" />
	```
	Add it below the comment: 
	```
	<!-- For rest api hook integration -->
	```
4. In `bolt-magento2/etc/webapi.xml` add the following block of code between the `routes` tag: 
	```
	<!-- Load test web hook -->  
    <route url="/V1/bolt/boltpay/order/cartdata" method="POST">
        <service class="Bolt\Boltpay\Api\LoadTestCartDataInterface" method="execute"/>
            <resources> 
                <resource ref="anonymous" />  
            </resources>
    </route>
	 ```
    NOTE: Make sure that you use spaces instead of tabs when indenting in the xml files 

5. In `bolt-magento2/etc/webapi_rest` add the following block of code between the `config` tag: 
	```
    <type name="Bolt\Boltpay\Helper\Cart">  
        <arguments>
             <argument name="checkoutSession" xsi:type="object">Magento\Checkout\Model\Session</argument>  
        </arguments>
    </type> 
	```

6. Switch over to `bolt-magento2/Test/Load/setup-files` directory and run `source setup.sh`
