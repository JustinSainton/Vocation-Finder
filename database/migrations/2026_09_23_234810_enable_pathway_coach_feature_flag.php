<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The pathway coach ships on.
 *
 * Only an existing row is switched: a fresh install has no flags yet, and
 * FeatureFlagSeeder creates this one enabled. The cached reads are dropped so
 * a deploy does not keep serving "off" for the cache's five minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feature_flags')
            ->where('key', 'pathway_coach')
            ->update(['is_enabled' => true, 'updated_at' => now()]);

        $this->forgetCachedFlags();
    }

    public function down(): void
    {
        DB::table('feature_flags')
            ->where('key', 'pathway_coach')
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        $this->forgetCachedFlags();
    }

    private function forgetCachedFlags(): void
    {
        Cache::forget('feature_flag:pathway_coach');
        Cache::forget('feature_flags:all');
    }
};
