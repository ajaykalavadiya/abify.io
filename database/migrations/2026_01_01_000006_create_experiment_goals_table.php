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
        Schema::create('experiment_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('match_type')->nullable();
            $table->string('target')->nullable();
            $table->timestamps();

            $table->index('experiment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_goals');
    }
};
