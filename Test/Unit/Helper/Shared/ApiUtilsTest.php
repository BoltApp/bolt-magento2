<?php
/**
 * Bolt magento2 plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Test\Unit\Helper\Shared;

use Bolt\Boltpay\Helper\Shared\ApiUtils;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * Class ApiUtilsTest
 *
 * @package Bolt\Boltpay\Test\Unit\Helper\Shared
 */
class ApiUtilsTest extends TestCase
{

    /**
     * @inheritdoc
     */
    public function testGetJSONFromResponseBody_success() {
        $body = "{\"status\":\"ok\"}";
        $parsedBody = ApiUtils::getJSONFromResponseBody($body);
        $this->assertObjectHasAttribute("status", $parsedBody);
        $this->assertEquals("ok", $parsedBody->{"status"});
    }

    /**
     * @inheritdoc
     */
    public function testGetJSONFromResponseBody_badJSON() {
        $body = '{"status": notok}';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("JSON Parse Error: Syntax error, malformed JSON");
        $result = ApiUtils::getJSONFromResponseBody($body);
        $this->assertEquals($result, null);
    }

    /**
     * @inheritdoc
     */
    public function testGetJSONFromResponseBody_errorFromBolt() {
        $body = '{"error": {"something": "here"}}';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageRegExp("/Bolt API Error.*/");
        $result = ApiUtils::getJSONFromResponseBody($body);
        $this->assertEquals($result, null);
    }

    /**
     * @inheritdoc
     */
    public function testGetJSONFromResponseBody_errorFromBoltWithMsg() {
        $body = '{"errors": [{"message": "here"}]}';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageRegExp("/here.*/");
        $result = ApiUtils::getJSONFromResponseBody($body);
        $this->assertEquals($result, null);
    }

    public function testConstructRequestHeaders_basic() {
        $headers = ApiUtils::constructRequestHeaders(
            "storeVersion",
            "moduleVersion",
            "request-data",
            "api-key",
            array()
        );
        $this->assertEquals($headers["User-Agent"], "BoltPay/Magento-storeVersion/moduleVersion");
        $this->assertEquals($headers["X-Bolt-Plugin-Version"], "moduleVersion");
        $this->assertEquals($headers["Content-Type"], "application/json");
        $this->assertEquals($headers["Content-Length"], 12);
        $this->assertEquals($headers["X-Api-Key"], "api-key");
    }

    public function testConstructRequestHeaders_skipsOverwritingWithAdditional() {
        $headers = ApiUtils::constructRequestHeaders(
            "storeVersion",
            "moduleVersion",
            "request-data",
            "api-key",
            array(
                "Content-type" => "secret",
                "new-thing" => "nothing"
            )
        );
        $this->assertEquals($headers["User-Agent"], "BoltPay/Magento-storeVersion/moduleVersion");
        $this->assertEquals($headers["X-Bolt-Plugin-Version"], "moduleVersion");
        $this->assertEquals($headers["Content-Type"], "application/json");
        $this->assertEquals($headers["Content-Length"], 12);
        $this->assertEquals($headers["X-Api-Key"], "api-key");
        $this->assertEquals($headers["new-thing"], "nothing");
    }
}
