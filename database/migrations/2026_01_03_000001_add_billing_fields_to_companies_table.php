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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index()->after('uid');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            // Generic (pre-subscription) trial — Cashier's onGenericTrial()
            // reads this directly; set to now()+14 days when the company is
            // created (see App\Actions\Tenancy\CreateCompanyWithOwner).
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
