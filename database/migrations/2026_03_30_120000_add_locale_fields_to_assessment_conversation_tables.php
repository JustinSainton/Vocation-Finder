<?php

use App\Support\ConversationLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('locale', 16)->default(ConversationLocale::DEFAULT)->after('status');
            $table->string('speech_locale', 16)->default(ConversationLocale::DEFAULT)->after('locale');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->string('response_locale', 16)->nullable()->after('response_text');
        });

        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->string('locale', 16)->default(ConversationLocale::DEFAULT)->after('status');
            $table->string('speech_locale', 16)->default(ConversationLocale::DEFAULT)->after('locale');
            $table->string('preferred_tts_voice', 64)->nullable()->after('speech_locale');
        });

        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->string('content_locale', 16)->nullable()->after('content');
            $table->decimal('content_confidence', 5, 4)->nullable()->after('content_locale');
            $table->string('stt_engine', 64)->nullable()->after('content_confidence');
            $table->json('metadata')->nullable()->after('stt_engine');
        });

        DB::table('assessments')->update([
            'locale' => ConversationLocale::DEFAULT,
            'speech_locale' => ConversationLocale::DEFAULT,
        ]);

        DB::table('conversation_sessions')->update([
            'locale' => ConversationLocale::DEFAULT,
            'speech_locale' => ConversationLocale::DEFAULT,
        ]);
    }

    public function down(): void
    {
        Schema::table('conversation_turns', function (Blueprint $table) {
            $table->dropColumn(['content_locale', 'content_confidence', 'stt_engine', 'metadata']);
        });

        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropColumn(['locale', 'speech_locale', 'preferred_tts_voice']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['response_locale']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['locale', 'speech_locale']);
        });
    }
};
