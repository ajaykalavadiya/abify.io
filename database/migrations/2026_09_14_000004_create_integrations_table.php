<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A connected commerce platform for one site.
     *
     * Deliberately generic rather than a `shopify_stores` table: WooCommerce
     * and Magento connect on the same terms — a remote store identity plus a
     * credential — and all three feed the same conversion API, so the only
     * thing that differs between them is the adapter that talks to them.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 32);

            // The store's own identifier on that platform — a myshopify
            // domain, a WooCommerce site URL. Unique per provider because a
            // given store can only be connected to one site at a time;
            // connecting it elsewhere must move it, not silently duplicate
            // the conversions.
            $table->string('external_id');

            $table->string('status', 16)->default('active');

            // Encrypted at rest: this is a token that can read a merchant's
            // orders, which is the most sensitive thing this product holds.
            $table->text('credentials')->nullable();

            // Free-form provider detail (shop name, currency, plan) that is
            // useful to display but never authenticates anything.
            $table->json('meta')->nullable();

            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['site_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
