<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gowns', function (Blueprint $table) {
            // Rental price per reservation, in pesos. Up to 99,999,999.99
            $table->decimal('rental_price', 10, 2)->default(0)->after('silhouette');
        });
    }

    public function down(): void
    {
        Schema::table('gowns', function (Blueprint $table) {
            $table->dropColumn('rental_price');
        });
    }
};
