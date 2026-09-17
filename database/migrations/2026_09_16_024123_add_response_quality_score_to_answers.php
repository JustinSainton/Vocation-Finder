<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quality here means evidentiary richness, never writing skill. The
     * breakdown is stored alongside the total so a low score can be explained
     * to an administrator rather than merely asserted.
     */
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedTinyInteger('response_quality_score')->nullable()->after('ai_preliminary_analysis');
            $table->json('response_quality_bands')->nullable()->after('response_quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['response_quality_score', 'response_quality_bands']);
        });
    }
};
