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
        Schema::create('visitor_assignments', function (Blueprint $table) {
            $table->id();
            // References Experiment/ExperimentVariation rows in the CENTRAL
            // database — plain indexed columns, no FK constraint possible
            // across physical databases.
            $table->unsignedBigInteger('experiment_id');
            $table->unsignedBigInteger('variation_id');
            $table->char('visitor_uuid', 36);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['experiment_id', 'visitor_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_assignments');
    }
};
