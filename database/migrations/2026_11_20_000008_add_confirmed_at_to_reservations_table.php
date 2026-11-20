<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // When the shop first confirmed the reservation. The reports use it to measure the wait.
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });

        // Reservations made before this update: the best available guess.
        // Walk-ins were confirmed when they were made.
        DB::table('reservations')
            ->where('source', 'walk_in')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('created_at')]);

        // Online ones that were confirmed (or went further): the last time they were touched.
        DB::table('reservations')
            ->whereIn('status', ['confirmed', 'picked_up', 'returned'])
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
