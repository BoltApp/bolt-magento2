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

## Integration tests

Unit test flow (above) is depricated. We are running all tests - magento unit and magento integration - in magento integration test flow

### Run integration tests locally

Create dedicated database for running the test

`CREATE DATABASE magento_integration_tests;`
`GRANT ALL ON magento_integration_tests.* TO 'magento2_test_user'@'localhost' IDENTIFIED BY '<your-password>';`

Copy configuration file template `mage2ce/dev/tests/integration/etc/install-config-mysql.php.dist` to 'install-config-mysql.php' in the same directory and add your test database access credentials.

Copy xml configuration file

`cp app/code/Bolt/Boltpay/Test/Unit/integration_phpunit.xml dev/tests/integration/bolt_phpunit.xml`

Run integration tests

`cd dev/tests/integration/`
`../../../vendor/bin/phpunit -c bolt_phpunit.xml`

### Notes:
1. Magento should be in developer mode.
2. When we run tests first time it works slowly because magento makes installation.
3. Change const TESTS_CLEANUP in file bolt_phpunit.xml to enabled when need to clean up.

Additional infomation:
https://devdocs.magento.com/guides/v2.4/test/integration/integration_test_execution.html
