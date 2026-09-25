<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI dimensional-mapping fields can exceed VARCHAR(255). Production failed
     * persisting a ~300+ character mode_of_work value (SQLSTATE 22001).
     *
     * Narrative sections already use text; widen the three dimensional string
     * columns to match. Drop btree indexes on primary_domain and mode_of_work
     * first — MySQL cannot index unbounded TEXT the same way.
     */
    public function up(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->dropIndex(['primary_domain']);
            $table->dropIndex(['mode_of_work']);
        });

        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->text('primary_domain')->nullable()->change();
            $table->text('mode_of_work')->nullable()->change();
            $table->text('secondary_orientation')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->string('primary_domain')->nullable()->change();
            $table->string('mode_of_work')->nullable()->change();
            $table->string('secondary_orientation')->nullable()->change();
        });

        Schema::table('vocational_profiles', function (Blueprint $table) {
            $table->index('primary_domain');
            $table->index('mode_of_work');
        });
    }
};
