<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The assessment now hands off to the coach, so the coach has to be there.
 *
 * `pathway_coach` was created off and never switched on, which meant every
 * route behind it answered 404 — including the one the results page now
 * leads to. The flag stays as a kill switch; this only moves its default.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updated = DB::table('feature_flags')->where('key', 'pathway_coach')->update([
            'is_enabled' => true,
            'updated_at' => now(),
        ]);

        if ($updated === 0) {
            DB::table('feature_flags')->insert([
                'id' => (string) Str::uuid(),
                'key' => 'pathway_coach',
                'name' => 'Pathway Coach (students)',
                'description' => 'The student-facing coach and vocational brain.',
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('feature_flag:pathway_coach');
        Cache::forget('feature_flags:all');
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', 'pathway_coach')->update(['is_enabled' => false]);

        Cache::forget('feature_flag:pathway_coach');
        Cache::forget('feature_flags:all');
    }
};
