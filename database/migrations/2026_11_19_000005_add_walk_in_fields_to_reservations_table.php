<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A walk-in customer may not have an account, so a reservation no longer needs a user.
        // The foreign key is dropped and added back around the change, which is the safe order in MySQL.
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            // online = booked by the customer on the website, walk_in = booked by staff at the shop
            $table->string('source', 20)->default('online')->after('status');

            // Who the customer is when they have no account (walk-ins)
            $table->string('customer_name')->nullable()->after('source');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_phone');

            // The staff member who made the booking, for the audit trail
            $table->foreignId('created_by')->nullable()->after('customer_note')
                ->constrained('users')->restrictOnDelete();

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'customer_name', 'customer_phone', 'customer_email', 'created_by']);
        });

        // Walk-in bookings have no user, so they cannot exist once a user is required again
        DB::table('reservations')->whereNull('user_id')->delete();

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
