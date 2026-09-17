<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blueprint roadmap item 0.7 — the taxonomy governs the AI. Before this
     * migration a category carried only a name and a description, which is not
     * enough to constrain an LLM's interpretation of a student's narrative.
     */
    public function up(): void
    {
        Schema::table('vocational_categories', function (Blueprint $table) {
            $table->text('core_function')->nullable()->after('description');
            $table->text('summary_sentence')->nullable()->after('core_function');
            $table->json('signal_fingerprint')->nullable()->after('summary_sentence');
            $table->json('distortions')->nullable()->after('signal_fingerprint');
            $table->json('adjacent_categories')->nullable()->after('distortions');
            $table->json('taxonomy_profile')->nullable()->after('adjacent_categories');
        });
    }

    public function down(): void
    {
        Schema::table('vocational_categories', function (Blueprint $table) {
            $table->dropColumn([
                'core_function',
                'summary_sentence',
                'signal_fingerprint',
                'distortions',
                'adjacent_categories',
                'taxonomy_profile',
            ]);
        });
    }
};
