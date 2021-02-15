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
 * @copyright  Copyright (c) 2017-2021 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Bolt\Boltpay\Helper;

/**
 * Helper for SSO
 */
class SSOHelper
{
    /**
     * Reference document: https://auth0.com/docs/tokens/json-web-tokens/validate-json-web-tokens#manually-implement-checks
     *
     * @param string $token    the JWT token
     * @param string $audience the token audience
     * @param string $pubkey   the pubkey to verify the token signature
     *
     * @return mixed|null object in JWT token body, return null if validation fails
     */
    public static function parseAndValidateJWT($token, $audience, $pubkey)
    {
        // 1. Check JWT is well-formed

        // 1.1 contains three parts separated by dot
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $encodedHeader = $parts[0];
        $encodedPayload = $parts[1];
        $encodedSignature = $parts[2];

        // 1.2 decode header
        $headerStr = base64_decode($encodedHeader);
        $header = json_decode($headerStr, true);

        // 1.2 decode payload
        $payloadStr = base64_decode($encodedPayload);
        $payload = json_decode($payloadStr, true);

        // 1.2 decode signature
        $signature = base64_decode($encodedSignature);

        // 2. Check standard claims

        // 2.1 make sure the expiration time must be after the current time
        if (!isset($payload['exp'])) {
            return null;
        }
        if (microtime() > $payload['exp'] * 1000) {
            return null;
        }

        // 2.2 issuing authority should be https://bolt.com
        if (!isset($payload['iss'])) {
            return null;
        }
        if ($payload['iss'] !== 'https://bolt.com') {
            return null;
        }

        // 2.3 aud should contain $audience
        if (!isset($payload['aud'])) {
            return null;
        }
        if (strpos($payload['aud'], $audience) === false) {
            return null;
        }

        // 3. Check signature

        // 3.1 check allowed algorithm (Bolt uses RSA-SHA256)
        if (!isset($header['alg'])) {
            return null;
        }
        if ($header['alg'] !== 'RS256') {
            return null;
        }

        // 3.2 verify hashed value
        $contentToVerify = $encodedHeader . '.' . $encodedPayload;
        $encodedContentToVerify = base64_encode($contentToVerify);
        if (openssl_verify($encodedContentToVerify, $signature, $pubkey, 'sha256WithRSAEncryption') !== 1) {
            return null;
        }

        // 4. Check fields exist
        if (!isset($payload['sub'])) {
            return null;
        }

        return $payload;
    }
}
