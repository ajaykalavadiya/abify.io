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
        Schema::create('goal_conversions', function (Blueprint $table) {
            $table->id();
            // References Experiment/ExperimentGoal/ExperimentVariation rows in
            // the CENTRAL database — plain indexed columns, no FK constraint.
            $table->unsignedBigInteger('experiment_id');
            $table->unsignedBigInteger('goal_id');
            $table->unsignedBigInteger('variation_id');
            $table->char('visitor_uuid', 36);
            $table->json('meta')->nullable();
            $table->timestamp('converted_at');

            $table->index(['experiment_id', 'goal_id', 'variation_id']);
            $table->index('visitor_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_conversions');
    }
};
