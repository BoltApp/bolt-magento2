## Magento2 Integration tests

Unit test flow is depricated. We are running all tests - magento unit and magento integration - in magento integration test flow

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
