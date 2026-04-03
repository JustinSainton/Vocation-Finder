<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('company_name');
            $table->string('company_url')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->string('salary_currency', 3)->default('USD');
            $table->text('description')->nullable();
            $table->text('description_plain')->nullable();
            $table->json('required_skills')->nullable();
            $table->string('source');
            $table->string('source_id');
            $table->string('source_url')->nullable();
            $table->string('soc_code')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('classification_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source', 'source_id']);
            $table->index('soc_code');
            $table->index('classification_status');
            $table->index(['is_remote', 'salary_min']);
            $table->index('posted_at');
        });

        Schema::create('job_listing_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vocational_category_id')->constrained()->cascadeOnDelete();
            $table->float('relevance_score')->default(0.5);
            $table->timestamps();

            $table->unique(['job_listing_id', 'vocational_category_id'], 'job_cat_unique');
        });

        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_jobs');
        Schema::dropIfExists('job_listing_categories');
        Schema::dropIfExists('job_listings');
    }
};
