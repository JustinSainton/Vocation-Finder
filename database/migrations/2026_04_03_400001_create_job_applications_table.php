<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('resume_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('cover_letter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('saved');
            $table->string('company_name');
            $table->string('job_title');
            $table->string('job_url')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->integer('salary_offered')->nullable();
            $table->text('notes')->nullable();
            $table->string('priority')->default('medium');
            $table->string('source')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('next_action_date');
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['job_application_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('job_applications');
    }
};
