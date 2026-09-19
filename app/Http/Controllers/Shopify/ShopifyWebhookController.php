<?php

namespace App\Http\Controllers\Shopify;

use App\Actions\Tracking\RecordConversion;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Support\Shopify\ShopifySignature;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Shopify webhooks.
 *
 * Orders are the trustworthy source of revenue: Shopify's checkout is
 * sandboxed on non-Plus stores, so no browser-side code of ours runs at the
 * moment of purchase. The order arrives here instead, hours later, on a
 * connection that knows nothing about the visitor — which is what the cart
 * attribute written on the storefront exists to bridge.
 */
class ShopifyWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ShopifySignature $signatures,
        TenantManager $tenants,
        RecordConversion $recorder,
    ): Response {
        // Verified against the raw body before anything is parsed or trusted.
        // Without this anyone who learns a shop domain could post fabricated
        // orders into that merchant's experiment results.
        if (! $signatures->verifyWebhook($request->getContent(), $request->header('X-Shopify-Hmac-Sha256'))) {
            return response('', 401);
        }

        $topic = (string) $request->header('X-Shopify-Topic');
        $shop = (string) $request->header('X-Shopify-Shop-Domain');

        $integration = Integration::where('provider', Integration::SHOPIFY)
            ->where('external_id', $shop)
            ->first();

        // 200 even when unknown: a non-2xx tells Shopify to retry, and it
        // will keep retrying for days over something no retry can fix.
        if (! $integration) {
            return response('', 200);
        }

        match ($topic) {
            'orders/paid', 'orders/create' => $this->recordOrder($request, $integration, $tenants, $recorder),
            'app/uninstalled' => $this->markUninstalled($integration),
            'shop/redact', 'customers/redact', 'customers/data_request' => $this->handlePrivacy($topic, $shop),
            default => null,
        };

        return response('', 200);
    }

    protected function recordOrder(
        Request $request,
        Integration $integration,
        TenantManager $tenants,
        RecordConversion $recorder,
    ): void {
        $order = $request->json()->all();

        $visitorUuid = $this->visitorFromOrder($order);

        // No attribute means the buyer never had our script run on the
        // storefront — an organic order, not evidence about any variation.
        if (! $visitorUuid) {
            return;
        }

        $site = $integration->site;

        if (! $site) {
            return;
        }

        $tenants->forSite($site);

        $recorder->byKey(
            site: $site,
            visitorUuid: $visitorUuid,
            goalKey: (string) config('shopify.purchase_goal_key'),
            // Shopify retries webhooks, and orders/create and orders/paid can
            // both fire for the same order. The order id is what collapses
            // them to one conversion.
            externalId: 'shopify:'.($order['id'] ?? ''),
            revenue: isset($order['total_price']) ? (float) $order['total_price'] : null,
            currency: $order['currency'] ?? null,
            props: [
                'order_number' => $order['order_number'] ?? null,
                'line_items' => is_array($order['line_items'] ?? null) ? count($order['line_items']) : null,
            ],
            // The shop's own timestamp, which carries its UTC offset.
            occurredAt: $order['processed_at'] ?? $order['created_at'] ?? null,
        );
    }

    /**
     * Pull the visitor id back out of the order.
     *
     * Written on the storefront into cart attributes, which Shopify carries
     * through checkout and surfaces on the order as note_attributes.
     *
     * @param  array<string, mixed>  $order
     */
    protected function visitorFromOrder(array $order): ?string
    {
        $key = (string) config('shopify.visitor_attribute');

        foreach ((array) ($order['note_attributes'] ?? []) as $attribute) {
            if (($attribute['name'] ?? null) === $key && ! empty($attribute['value'])) {
                return (string) $attribute['value'];
            }
        }

        return null;
    }

    protected function markUninstalled(Integration $integration): void
    {
        // Revoked, not deleted: the conversions this store produced are still
        // real results that finished experiments are reported from.
        $integration->forceFill([
            'status' => Integration::STATUS_REVOKED,
            'credentials' => null,
        ])->save();
    }

    /**
     * Shopify requires all three GDPR topics to be handled before an app can
     * be listed.
     *
     * abify stores no Shopify customer identifiers — orders are reduced to a
     * visitor uuid, a total and a currency on the way in — so there is
     * nothing to return or erase. Logged so that answer is evidenced rather
     * than asserted.
     */
    protected function handlePrivacy(string $topic, string $shop): void
    {
        Log::info('Shopify privacy webhook acknowledged', [
            'topic' => $topic,
            'shop' => $shop,
        ]);
    }
}
