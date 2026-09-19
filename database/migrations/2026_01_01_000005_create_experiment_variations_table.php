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
        Schema::create('experiment_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->boolean('is_control')->default(false);
            $table->unsignedTinyInteger('traffic_weight')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['experiment_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_variations');
    }
};
