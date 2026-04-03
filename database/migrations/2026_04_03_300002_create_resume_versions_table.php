<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('resume_data');
            $table->string('file_path_pdf')->nullable();
            $table->string('file_path_docx')->nullable();
            $table->json('generation_context')->nullable();
            $table->float('quality_score')->nullable();
            $table->string('status')->default('generating');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'job_listing_id']);
            $table->index('status');
        });

        Schema::create('cover_letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('file_path_pdf')->nullable();
            $table->string('file_path_docx')->nullable();
            $table->json('generation_context')->nullable();
            $table->json('company_research')->nullable();
            $table->float('quality_score')->nullable();
            $table->string('status')->default('generating');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'job_listing_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_letters');
        Schema::dropIfExists('resume_versions');
    }
};
