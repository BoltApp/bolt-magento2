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

namespace Bolt\Boltpay\Test\Unit\Helper;

use Bolt\Boltpay\Helper\SSOHelper;
use Bolt\Boltpay\Test\Unit\BoltTestCase;

/**
 * @coversDefaultClass \Bolt\Boltpay\Helper\SSOHelper
 */
class SSOHelperTest extends BoltTestCase
{
    /**
     * @test
     *
     * @covers ::parseAndValidateJWT
     *
     * @dataProvider parseAndValidateJWTProvider
     *
     * @param string     $token
     * @param string     $audience
     * @param string     $pubkey
     * @param mixed|null $expected
     */
    public function parseAndValidateJWT_returnsCorrectValue_forAllCases($token, $audience, $pubkey, $expected)
    {
        $this->assertEquals($expected, SSOHelper::parseAndValidateJWT($token, $audience, $pubkey));
    }

    /**
     * Data provider for {@see parseAndValidateJWT_returnsCorrectValue_forAllCases}
     *
     * @return array
     */
    public function parseAndValidateJWTProvider()
    {
        $wrongSigAndPubkey = $this->getSignatureAndPublicKey(base64_encode('{"alg":"RS256"}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx"}'));
        $rightSigAndPubkey = $this->getSignatureAndPublicKey(base64_encode('{"alg":"RS256"}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx","sub":"test sub"}'));
        return [
            [
                'token'    => '',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{}') . '.',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{"exp":0}') . '.',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{"exp":2000000000}') . '.',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{"exp":2000000000,"iss":"not bolt"}') . '.',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com"}') . '.',
                'audience' => '',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"blah"}') . '.',
                'audience' => 'test audience',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => base64_encode('{}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx"}') . '.',
                'audience' => 'test audience',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => base64_encode('{"alg":"random"}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx"}') . '.',
                'audience' => 'test audience',
                'pubkey'   => '',
                'expected' => null
            ],
            [
                'token'    => base64_encode('{"alg":"RS256"}') . '.' . base64_encode('{"exp":2000000001,"iss":"https://bolt.com","aud":"xxtest audiencexx"}') . '.' . $wrongSigAndPubkey['sig'],
                'audience' => 'test audience',
                'pubkey'   => $wrongSigAndPubkey['pubkey'],
                'expected' => null
            ],
            [
                'token'    => base64_encode('{"alg":"RS256"}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx"}') . '.' . $wrongSigAndPubkey['sig'],
                'audience' => 'test audience',
                'pubkey'   => $wrongSigAndPubkey['pubkey'],
                'expected' => null
            ],
            [
                'token'    => base64_encode('{"alg":"RS256"}') . '.' . base64_encode('{"exp":2000000000,"iss":"https://bolt.com","aud":"xxtest audiencexx","sub":"test sub"}') . '.' . $rightSigAndPubkey['sig'],
                'audience' => 'test audience',
                'pubkey'   => $rightSigAndPubkey['pubkey'],
                'expected' => [
                    'exp' => 2000000000,
                    'iss' => 'https://bolt.com',
                    'aud' => 'xxtest audiencexx',
                    'sub' => 'test sub'
                ]
            ]
        ];
    }

    private function getSignatureAndPublicKey($data)
    {
        $private_key_res = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));
        openssl_sign(base64_encode($data), $signature, $private_key_res, OPENSSL_ALGO_SHA256);
        return [
            'sig'    => base64_encode($signature),
            'pubkey' => openssl_pkey_get_details($private_key_res)['key']
        ];
    }
}
