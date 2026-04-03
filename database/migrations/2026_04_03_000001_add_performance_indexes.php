<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('completed_at');
            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('assessment_surveys', function (Blueprint $table) {
            $table->index(['assessment_id', 'type']);
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['organization_id', 'status']);
        });

        Schema::table('assessment_surveys', function (Blueprint $table) {
            $table->dropIndex(['assessment_id', 'type']);
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'role']);
        });
    }
};
