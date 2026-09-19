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
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('status')->default('draft');
            $table->string('type')->default('ab');
            $table->unsignedTinyInteger('traffic_allocation')->default(100);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
            $table->index(['company_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
