<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('mentor_id');
            $table->uuid('student_id');
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->restrictOnDelete();

            // One active mentor per student per org
            $table->unique(['organization_id', 'student_id', 'status'], 'mentor_assignments_active_unique');
            $table->index('mentor_id');
            $table->index(['organization_id', 'mentor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_assignments');
    }
};
