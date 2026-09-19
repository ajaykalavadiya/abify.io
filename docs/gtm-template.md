# Sending conversions to abify from Google Tag Manager

This is the zero-code conversion path. It works on any platform — WooCommerce,
Magento, a custom stack — and needs no plugin.

A caveat specific to Shopify: GTM does not reliably run on Shopify's checkout,
so Shopify stores should use the Shopify integration's order webhook for
purchases and keep GTM for everything before checkout.

## The tag

**Tag type:** Custom HTML
**Trigger:** your existing GA4 `purchase` dataLayer event

```html
<script>
  (function () {
    if (typeof window.abify !== 'function') return;

    window.abify('goal', {{DLV - abify goal id}}, {
      revenue:  {{DLV - ecommerce.value}},
      currency: {{DLV - ecommerce.currency}},
      // The order id is what makes a retried or duplicated tag fire harmless:
      // abify deduplicates conversions on it.
      order_id: {{DLV - ecommerce.transaction_id}}
    });
  })();
</script>
```

`abify goal id` is the numeric id of the goal, shown next to it on the
experiment page. The conversion is recorded against every running experiment
the visitor is enrolled in, so one tag covers all of your tests.

## Reading results by variation in GA4

Turn on **Push assignments to the GTM dataLayer** on the site's overview page.
Every assignment then fires:

```js
{
  event: 'abify.experimentViewed',
  abifyExperiment:   'homepage-hero',
  abifyExperimentId: 42,
  abifyVariation:    'variant-b',
  abifyVariationId:  87
}
```

Turning on **Send an event to Google Analytics 4** additionally fires an
`experiment_viewed` event through the `gtag` already on your page, and sets an
`abify_variation` user property.

abify never loads its own GA4 — that would give you a second, competing
tracker and double every pageview you report.

> **This step is required.** Register `experiment_key` and `variation_key` as
> custom dimensions in GA4 Admin → Custom definitions. Without that, GA4
> receives the data and silently declines to show it in any report. This is
> the single most common support question for this feature.

## Installing abify itself through GTM

Supported, but not recommended. GTM loads asynchronously, so the anti-flicker
hide runs late and visitors see the original page paint before the variation
is applied — the flicker the snippet exists to prevent. Put the snippet in your
theme directly, and keep GTM for conversions.
