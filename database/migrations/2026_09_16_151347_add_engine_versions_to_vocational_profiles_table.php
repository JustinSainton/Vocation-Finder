<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 0.4. A profile that cannot say which engine produced it cannot be
 * compared with the profile produced next month, so no prompt change can ever
 * be shown to have helped.
 *
 * Nullable because every profile written before today genuinely has no known
 * version, and inventing one would be worse than admitting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->string('model_version')->nullable()->after('ai_analysis_raw');
            $table->string('prompt_version', 12)->nullable()->after('model_version');
            $table->string('taxonomy_version', 12)->nullable()->after('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->dropColumn(['model_version', 'prompt_version', 'taxonomy_version']);
        });
    }
};
