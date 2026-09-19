<?php

namespace Tests\Unit;

use App\Support\Shopify\ShopifySignature;
use PHPUnit\Framework\TestCase;

class ShopifySignatureTest extends TestCase
{
    private const SECRET = 'shpss_test_secret';

    private ShopifySignature $signatures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signatures = new ShopifySignature(self::SECRET);
    }

    public function test_it_accepts_a_correctly_signed_webhook(): void
    {
        $body = '{"id":1,"total_price":"10.00"}';
        $header = base64_encode(hash_hmac('sha256', $body, self::SECRET, true));

        $this->assertTrue($this->signatures->verifyWebhook($body, $header));
    }

    /**
     * Webhooks are signed over the exact bytes sent — re-encoding parsed JSON
     * changes key order and spacing and can never match.
     */
    public function test_a_reserialised_body_no_longer_verifies(): void
    {
        $body = '{"id":1,"total_price":"10.00"}';
        $header = base64_encode(hash_hmac('sha256', $body, self::SECRET, true));

        $reserialised = json_encode(json_decode($body, true), JSON_PRETTY_PRINT);

        $this->assertFalse($this->signatures->verifyWebhook($reserialised, $header));
    }

    public function test_it_rejects_a_missing_or_wrong_signature(): void
    {
        $body = '{"id":1}';

        $this->assertFalse($this->signatures->verifyWebhook($body, null));
        $this->assertFalse($this->signatures->verifyWebhook($body, ''));
        $this->assertFalse($this->signatures->verifyWebhook($body, 'AAAA'));
    }

    /**
     * OAuth uses a completely different scheme from webhooks — sorted query
     * string, hex digest. Neither signature validates the other.
     */
    public function test_it_verifies_an_oauth_redirect(): void
    {
        $query = ['code' => 'abc', 'shop' => 'demo.myshopify.com', 'state' => 'xyz', 'timestamp' => '1700000000'];

        $signed = $query;
        ksort($signed);
        $query['hmac'] = hash_hmac('sha256', http_build_query($signed), self::SECRET);

        $this->assertTrue($this->signatures->verifyOAuth($query));
    }

    public function test_a_tampered_oauth_parameter_fails(): void
    {
        $query = ['code' => 'abc', 'shop' => 'demo.myshopify.com', 'state' => 'xyz'];

        $signed = $query;
        ksort($signed);
        $query['hmac'] = hash_hmac('sha256', http_build_query($signed), self::SECRET);

        $query['shop'] = 'attacker.myshopify.com';

        $this->assertFalse($this->signatures->verifyOAuth($query));
    }

    /**
     * The shop domain is merchant-supplied and ends up in an outbound
     * redirect, so anything that is not clearly a myshopify host is refused
     * before it can send someone elsewhere.
     */
    public function test_it_only_accepts_myshopify_domains(): void
    {
        $this->assertTrue($this->signatures->isValidShopDomain('demo-store.myshopify.com'));

        foreach ([
            'evil.com',
            'demo.myshopify.com.evil.com',
            'demo.myshopify.com/../x',
            'https://demo.myshopify.com',
            '-bad.myshopify.com',
            null,
        ] as $bad) {
            $this->assertFalse(
                $this->signatures->isValidShopDomain($bad),
                'Accepted an unsafe shop domain: '.var_export($bad, true)
            );
        }
    }
}
