<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_access_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('photo_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('web_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('orientation')->nullable();
            $table->timestamp('taken_at')->nullable()->index();
            $table->string('camera_make')->nullable();
            $table->string('camera_model')->nullable();
            $table->string('lens')->nullable();
            $table->decimal('focal_length', 8, 2)->nullable();
            $table->unsignedSmallInteger('focal_length_35mm')->nullable();
            $table->decimal('aperture', 5, 2)->nullable();
            $table->string('shutter_speed')->nullable();
            $table->unsignedInteger('iso')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('copyright_holder');
            $table->string('copyright_notice');
            $table->string('credit_line')->nullable();
            $table->string('license');
            $table->text('usage_terms')->nullable();
            $table->string('processing_status')->index();
            $table->text('processing_error')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('station_id');
            $table->index('station_access_id');
            $table->index('photo_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
