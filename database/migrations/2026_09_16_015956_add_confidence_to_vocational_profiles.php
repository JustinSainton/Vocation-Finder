<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blueprint 8.6 requires the engine to carry a confidence level and to be
     * able to explain it. Blueprint 11.4 forbids ever locking the level, the
     * reasoning, or the stated limitations behind payment, so all three live
     * on the profile itself rather than in a paid section.
     */
    public function up(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->string('confidence_level')->nullable()->after('category_scores');
            $table->text('confidence_rationale')->nullable()->after('confidence_level');
            $table->json('missing_evidence')->nullable()->after('confidence_rationale');
        });
    }

    public function down(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->dropColumn(['confidence_level', 'confidence_rationale', 'missing_evidence']);
        });
    }
};
