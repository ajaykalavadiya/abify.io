<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            // References the Site row in the CENTRAL database — plain indexed
            // column, no FK constraint.
            $table->unsignedBigInteger('site_id');
            $table->char('visitor_uuid', 36);
            $table->char('session_id', 36)->nullable();
            $table->string('event_type');
            $table->string('event_name')->nullable();
            $table->string('url', 2048);
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'occurred_at']);
            $table->index(['site_id', 'event_name']);
            $table->index('visitor_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
