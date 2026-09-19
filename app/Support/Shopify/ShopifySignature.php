<?php

namespace App\Support\Shopify;

/**
 * Verifies that a request genuinely came from Shopify.
 *
 * Two different signing schemes, which is a common source of bugs: OAuth
 * redirects are signed as a sorted query string hashed to hex, while webhooks
 * are signed over the raw request body and base64 encoded. Neither validates
 * the other.
 */
class ShopifySignature
{
    public function __construct(protected ?string $secret = null)
    {
        $this->secret = $secret ?? config('shopify.secret');
    }

    /**
     * Webhook authenticity, over the exact bytes Shopify sent.
     *
     * Must be given the RAW body — re-encoding the parsed JSON changes key
     * order and whitespace and will never match.
     */
    public function verifyWebhook(string $rawBody, ?string $header): bool
    {
        if (! $this->secret || ! $header) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $this->secret, true));

        // Constant time: a plain === leaks how much of the signature matched,
        // which is enough to forge one a byte at a time.
        return hash_equals($expected, $header);
    }

    /**
     * OAuth redirect authenticity.
     *
     * @param  array<string, mixed>  $query
     */
    public function verifyOAuth(array $query): bool
    {
        $provided = $query['hmac'] ?? null;

        if (! $this->secret || ! is_string($provided)) {
            return false;
        }

        // 'hmac' and the legacy 'signature' are excluded from the payload
        // they sign; everything else is sorted by key.
        unset($query['hmac'], $query['signature']);
        ksort($query);

        $expected = hash_hmac('sha256', http_build_query($query), $this->secret);

        return hash_equals($expected, $provided);
    }

    /**
     * Shopify shop domains are attacker-supplied on the install route, and
     * they end up in an outbound URL — so anything that is not clearly a
     * myshopify host is rejected before it can redirect somewhere else.
     */
    public function isValidShopDomain(?string $shop): bool
    {
        return is_string($shop)
            && (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/', $shop);
    }
}
