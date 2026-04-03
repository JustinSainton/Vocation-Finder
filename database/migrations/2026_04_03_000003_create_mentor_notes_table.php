<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('mentor_id');
            $table->uuid('student_id');
            $table->text('content');
            $table->string('visibility')->default('mentor_only');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'student_id']);
            $table->index(['mentor_id', 'student_id']);
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_notes');
    }
};
