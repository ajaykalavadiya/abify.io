<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A stable, human-writable identifier for a goal.
     *
     * Server-side conversions arrive from a merchant's store, which knows it
     * sold something but has no idea what abify's internal goal ids are. The
     * key is the contract those integrations address — a Shopify order
     * webhook reports `purchase`, and every running experiment measuring a
     * goal keyed `purchase` records it.
     */
    public function up(): void
    {
        Schema::table('experiment_goals', function (Blueprint $table) {
            $table->string('key')->nullable()->after('experiment_id');
        });

        $this->backfillKeys();

        Schema::table('experiment_goals', function (Blueprint $table) {
            $table->unique(['experiment_id', 'key']);
        });
    }

    /**
     * Derive a key from each existing goal's name, per experiment. Names are
     * free text and two goals on one experiment can share one, so collisions
     * fall back to the goal id rather than failing the unique index below.
     */
    protected function backfillKeys(): void
    {
        DB::table('experiment_goals')
            ->select('id', 'experiment_id', 'name')
            ->orderBy('id')
            ->chunk(500, function ($goals) {
                $seen = [];

                foreach ($goals as $goal) {
                    $key = Str::slug((string) $goal->name) ?: 'goal';

                    $taken = isset($seen[$goal->experiment_id][$key])
                        || DB::table('experiment_goals')
                            ->where('experiment_id', $goal->experiment_id)
                            ->where('key', $key)
                            ->exists();

                    if ($taken) {
                        $key = "{$key}-{$goal->id}";
                    }

                    $seen[$goal->experiment_id][$key] = true;

                    DB::table('experiment_goals')->where('id', $goal->id)->update(['key' => $key]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('experiment_goals', function (Blueprint $table) {
            $table->dropUnique(['experiment_id', 'key']);
            $table->dropColumn('key');
        });
    }
};
