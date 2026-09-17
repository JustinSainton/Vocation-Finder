<?php

use App\Enums\ArtifactKind;
use App\Models\Artifact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The locker: what the student has made.
     *
     * An artifact is either a stored file or a link to one somewhere else, and
     * exactly one of the two. Both nullable in the schema because no column
     * can express "one of these", so {@see Artifact} refuses the
     * save — the same shape as the habit check-in's immutability rule.
     *
     * `disk` is stored per row rather than read from config at read time. A
     * file written to `local` in June does not move because someone changed
     * `FILESYSTEM_DISK` in September, and a row that has forgotten where its
     * file is is a lost artifact.
     */
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->enum('kind', ArtifactKind::values());
            $table->string('title');
            $table->text('note')->nullable();

            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->text('link_url')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
