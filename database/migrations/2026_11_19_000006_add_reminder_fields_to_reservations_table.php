<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Set when a reminder goes out, so the same reminder is never sent twice
            $table->timestamp('pickup_reminded_at')->nullable()->after('returned_at');
            $table->timestamp('return_reminded_at')->nullable()->after('pickup_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['pickup_reminded_at', 'return_reminded_at']);
        });
    }
};
