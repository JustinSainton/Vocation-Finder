<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sanctum's stock migration calls `morphs('tokenable')`, which creates
     * `tokenable_id` as an unsigned big integer. Every model in this
     * application keys on UUIDs (`HasUuids`, `$table->uuid('id')->primary()`),
     * so the two cannot meet. MySQL truncates the UUID on insert:
     *
     *   SQLSTATE[01000]: Warning: 1265 Data truncated for column
     *   'tokenable_id' at row 1
     *
     * `createToken()` therefore threw on every call, which meant no API token
     * had ever been issued on production and every mobile login and
     * registration returned a 500. The suite never caught it because PHPUnit
     * runs SQLite, which stores a UUID in an INTEGER column without complaint:
     * the bug was invisible everywhere except the one place it mattered.
     *
     * `uuidMorphs` is the shape this schema needed from the start. The create
     * migration now says so for fresh installs; this repairs the existing one.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('tokenable_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tokenable_id')->change();
        });
    }
};
