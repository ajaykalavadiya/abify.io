<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            // Multivariate tests only: the factors (elements) being tested and
            // each factor's options. The combinations generated from these are
            // stored as ordinary variations, so the tracking script needs no
            // knowledge of factors — see App\Support\Testing\CombinationBuilder.
            $table->json('factors')->nullable()->after('traffic_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            $table->dropColumn('factors');
        });
    }
};
