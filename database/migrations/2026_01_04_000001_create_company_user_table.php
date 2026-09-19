<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        // Backfill from the old single company_id/role columns before
        // dropping them, so existing memberships aren't lost.
        DB::table('users')
            ->whereNotNull('company_id')
            ->select('id', 'company_id', 'role')
            ->orderBy('id')
            ->get()
            ->each(function ($user) {
                DB::table('company_user')->insert([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('role')->default('owner')->after('company_id');
        });

        DB::table('company_user')
            ->orderBy('id')
            ->get()
            ->each(function ($membership) {
                DB::table('users')->where('id', $membership->user_id)->update([
                    'company_id' => $membership->company_id,
                    'role' => $membership->role,
                ]);
            });

        Schema::dropIfExists('company_user');
    }
};
