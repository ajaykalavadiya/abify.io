<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-site, per-day session recording usage.
     *
     * Lives in the CENTRAL database, not the tenant one, for two reasons:
     * billing reads it across every company at once and should not have to
     * switch connections per site to do it, and the quota check runs on an
     * ingestion request that must not depend on the tenant connection being
     * resolved before it can say "no".
     *
     * Durable rather than cached deliberately — this is the number invoices
     * are raised from, and a cache eviction mid-month would quietly reset a
     * customer's usage to zero.
     */
    public function up(): void
    {
        Schema::create('recording_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');

            // Incremented when a recording is admitted, which is the billable
            // event — a visit that was sampled out or refused never counts.
            $table->unsignedInteger('sessions_recorded')->default(0);
            $table->unsignedBigInteger('bytes_stored')->default(0);

            $table->timestamps();

            // The quota reservation is a single INSERT ... ON DUPLICATE KEY
            // UPDATE against this index, so two concurrent visitors cannot
            // both be admitted as the hundredth recording of the day.
            $table->unique(['site_id', 'usage_date']);
            $table->index(['company_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_usage');
    }
};
