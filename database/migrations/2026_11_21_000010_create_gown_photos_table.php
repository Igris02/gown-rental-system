<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Up to 2 extra photos per gown (position 1 and 2). The main photo stays on the gowns table.
        Schema::create('gown_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gown_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('image_url');
            $table->string('image_public_id');
            $table->unique(['gown_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gown_photos');
    }
};
