<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blueprint 10.3 — the distance between wanting and having done.
     *
     * Stored rather than derived on read because it is the input to the
     * development plan, and a plan whose premise silently changes when the
     * taxonomy is re-seeded is not a plan. The per-category tracks live inside
     * the existing `category_scores` JSON; this column holds only the leading
     * pathway's standing, which is the one the student is ever handed.
     */
    public function up(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->json('evidence_gap')->nullable()->after('missing_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->dropColumn('evidence_gap');
        });
    }
};
