<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gowns', function (Blueprint $table) {
            $table->id();

            // Short code staff can use to find a gown, such as G-001
            $table->string('gown_code', 20)->unique();

            $table->string('name');
            $table->string('design');                 // style or pattern, such as "Floral lace" or "Beaded bodice"
            $table->string('color', 50);
            $table->string('size', 20);               // XS, S, M, L, XL or a number
            $table->string('silhouette', 50);         // A-line, Ball gown, Mermaid, etc.
            $table->text('description')->nullable();

            // Cloudinary: the URL is shown on the site, the public id is needed to replace or delete the photo
            $table->string('image_url')->nullable();
            $table->string('image_public_id')->nullable();

            // Business status of the gown itself. Date-based availability comes from reservations later.
            // available = can be reserved, maintenance = being cleaned or repaired, retired = no longer offered
            $table->string('status', 20)->default('available');

            $table->timestamps();
            $table->softDeletes();                    // deleted gowns stay in old reservation and audit records

            // Speeds up catalog filtering
            $table->index('status');
            $table->index('color');
            $table->index('size');
            $table->index('silhouette');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gowns');
    }
};
