<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Storage\DatabaseConversationStore;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The coach's memory has to physically exist.
 *
 * Both failures pinned here were found by running the coach against a live
 * model for the first time, and neither was visible from reading the code:
 *
 * 1. The AI SDK's migration ships inside the package and had never been
 *    published, so `agent_conversations` did not exist. Every call to the
 *    coach — the pathway coach and the older career coach alike — died on the
 *    first query. The coach had never completed a turn.
 * 2. The published migration declares `user_id` as `foreignId()`, a bigint.
 *    Every user here has a UUID primary key. SQLite's loose typing would have
 *    hidden that indefinitely while Postgres and MySQL rejected it, which is
 *    the worst shape a bug can take: green locally, broken only in production.
 */
class CoachConversationStorageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_conversation_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('agent_conversations'));
        $this->assertTrue(Schema::hasTable('agent_conversation_messages'));
    }

    /**
     * Asserted against the schema rather than a round-trip, because SQLite
     * would happily store a UUID in an integer column and report success.
     */
    #[Test]
    public function the_conversation_tables_key_users_by_uuid_not_integer(): void
    {
        foreach (['agent_conversations', 'agent_conversation_messages'] as $table) {
            $this->assertNotContains(
                Schema::getColumnType($table, 'user_id'),
                ['integer', 'bigint', 'biginteger'],
                "[{$table}.user_id] is an integer column, but users are keyed by UUID.",
            );
        }
    }

    #[Test]
    public function a_conversation_round_trips_for_a_uuid_keyed_user(): void
    {
        $user = User::factory()->create();
        $store = new DatabaseConversationStore;

        $this->assertNull($store->latestConversationId($user->id));

        $conversationId = $store->storeConversation($user->id, 'A first session');

        $this->assertSame($conversationId, $store->latestConversationId($user->id));
    }

    /**
     * One student, one ongoing conversation: resuming has to resolve to the
     * thread they were last in, or the coach reintroduces itself forever.
     */
    #[Test]
    public function resuming_resolves_to_the_most_recent_conversation(): void
    {
        $user = User::factory()->create();
        $store = new DatabaseConversationStore;

        $store->storeConversation($user->id, 'An older session');
        $this->travel(1)->minutes();
        $newest = $store->storeConversation($user->id, 'The session they are in now');

        $this->assertSame($newest, $store->latestConversationId($user->id));
    }

    #[Test]
    public function one_students_conversation_is_never_another_students(): void
    {
        $store = new DatabaseConversationStore;
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $store->storeConversation($mine->id, 'Mine');

        $this->assertNull($store->latestConversationId($theirs->id));
    }
}
