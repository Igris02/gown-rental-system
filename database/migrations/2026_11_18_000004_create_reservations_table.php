<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Records are kept for reports and audit logs, so users and gowns cannot be hard-deleted
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('gown_id')->constrained()->restrictOnDelete();

            // A rental is 3 days: Day 1 pick up (start_date), Day 2 use, Day 3 return (end_date)
            $table->date('start_date');
            $table->date('end_date');

            // pending, confirmed, picked_up, returned, cancelled, rejected
            $table->string('status', 20)->default('pending');

            // The price at the time of booking, so later price changes do not alter old records
            $table->decimal('rental_price', 10, 2);

            $table->text('customer_note')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            // Speeds up the "is this gown free on these days?" check and the admin list
            $table->index(['gown_id', 'start_date', 'end_date']);
            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
