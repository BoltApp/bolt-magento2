## Magento2 PHPUnit tests

Magento 2 has lots of useful features which go pre-installed for unit tests.
To get started you need to copy prepared configuration file.


### Bolt configuration file:
here: `[magento2-folder]/app/code/Bolt/Boltpay/Test/Unit/phpunit.xml`

Copy this file to magento test folder `[magento2-folder]/dev/tests/unit/bolt_phpunit.xml` 

Now using this configuration you can run a test. You can do it via console using the phpunit command. 
It is recommended that you run the phpunit of the version that is available in Magento 2 repository.

### Run tests:
Use the following command to run our test from root magento2 project folder:
`php vendor/bin/phpunit -c dev/tests/unit/bolt_phpunit.xml`

If you want create a coverage report please run a command:
`php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml --coverage-html app/code/Bolt/Boltpay/Test/Unit/coverage`

After that you can find (or open in your browser) html report in folder `[magento2-folder]/app/code/Bolt/Boltpay/Test/Unit/coverage`

#### _Additional info for phpunit test_:
> _**Running Unit Tests in PHPStorm**_ 
> http://devdocs.magento.com/guides/v2.2/test/unit/unit_test_execution_phpstorm.html

> _**Usefull articles to start write phpunit tests for magneto2**_ 
> https://jtreminio.com/2013/03/unit-testing-tutorial-introduction-to-phpunit/