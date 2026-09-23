<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The web and Expo clients import their payload types from a committed file
 * generated from `app/Data`. A Data object changed without regenerating it is
 * the drift the generation exists to prevent.
 */
class GeneratedTypeScriptTest extends TestCase
{
    #[Test]
    public function the_committed_typescript_matches_the_data_objects(): void
    {
        $path = resource_path('js/types/generated.ts');
        $committed = file_get_contents($path);

        try {
            $this->artisan('typescript:transform')->assertSuccessful();

            $this->assertSame(
                $committed,
                file_get_contents($path),
                'resources/js/types/generated.ts is stale. Run `php artisan typescript:transform` and commit the result.',
            );
        } finally {
            file_put_contents($path, $committed);
        }
    }
}
