<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gown_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gown_id')->constrained()->cascadeOnDelete();
            $table->string('size', 20);
            $table->unique(['gown_id', 'size']);
            $table->index('size');
        });

        Schema::table('gowns', function (Blueprint $table) {
            // single = one piece fits every listed size, separate = one gown per size
            $table->string('size_type', 20)->default('single')->after('silhouette');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('size', 20)->nullable()->after('gown_id');
        });

        // Keep existing data: each gown's old size becomes its only size, and old reservations get that size
        DB::statement('INSERT INTO gown_sizes (gown_id, size) SELECT id, size FROM gowns');
        DB::statement('UPDATE reservations SET size = (SELECT size FROM gowns WHERE gowns.id = reservations.gown_id)');

        Schema::table('gowns', function (Blueprint $table) {
            $table->dropIndex(['size']);
            $table->dropColumn('size');
        });
    }

    public function down(): void
    {
        Schema::table('gowns', function (Blueprint $table) {
            $table->string('size', 20)->default('M')->after('color');
            $table->index('size');
        });

        DB::statement('UPDATE gowns SET size = COALESCE((SELECT MIN(size) FROM gown_sizes WHERE gown_sizes.gown_id = gowns.id), \'M\')');

        Schema::table('gowns', function (Blueprint $table) {
            $table->dropColumn('size_type');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('size');
        });

        Schema::dropIfExists('gown_sizes');
    }
};
