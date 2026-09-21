<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A token has to be storable in the column that holds it.
 *
 * This exists because the bug it guards against was invisible to every other
 * test in this suite. Sanctum's stock migration types `tokenable_id` as an
 * unsigned big integer, and every model here keys on UUIDs. SQLite — which is
 * what PHPUnit runs on — stores a UUID in an INTEGER column without complaint,
 * so the whole suite stayed green while production MySQL truncated the value
 * and answered every login and registration with a 500. No happy-path test can
 * see the difference. Only the column type can.
 */
class SanctumTokenSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tokenable_id_is_wide_enough_to_hold_a_uuid(): void
    {
        $type = strtolower((string) Schema::getColumnType('personal_access_tokens', 'tokenable_id'));

        $this->assertNotContains(
            $type,
            ['integer', 'int', 'bigint', 'bigint unsigned', 'mediumint', 'smallint', 'tinyint'],
            "personal_access_tokens.tokenable_id is typed '{$type}', which cannot hold a UUID. "
            .'Sanctum ships morphs(); this schema needs uuidMorphs().'
        );
    }

    #[Test]
    public function a_token_can_be_issued_for_a_uuid_keyed_user(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('mobile');

        $this->assertSame(
            $user->id,
            $token->accessToken->tokenable_id,
            'The stored tokenable_id must round-trip the user UUID unchanged.'
        );

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
            'tokenable_id' => $user->id,
        ]);
    }
}
