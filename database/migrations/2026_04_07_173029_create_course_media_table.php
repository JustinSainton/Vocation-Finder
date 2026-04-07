<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('type'); // image, pdf, video, document, presentation
            $table->json('metadata')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_media');
    }
};
