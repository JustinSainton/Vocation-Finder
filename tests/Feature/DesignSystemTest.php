<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The design system, checked against the document that governs it.
 *
 * `DESIGN.md` is the source of truth and `resources/css/app.css` says so in a
 * comment. A comment is not enforcement, so this reads the palette out of
 * DESIGN.md and asserts the stylesheet agrees — the same posture as everywhere
 * else in this engine: never declare by hand a value you can derive.
 *
 * The rest pins the decisions a future change is most likely to undo by
 * accident: that labels come from one class rather than six utilities, that
 * the dark room is actually inhabited, and that the single accent stays
 * single.
 */
class DesignSystemTest extends TestCase
{
    protected function stylesheet(): string
    {
        return (string) file_get_contents(base_path('resources/css/app.css'));
    }

    /**
     * CSS custom property name => the name it carries in DESIGN.md's palette.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            '--color-background' => 'canvas',
            '--color-surface' => 'surface-soft',
            '--color-surface-card' => 'surface-card',
            '--color-text' => 'ink',
            '--color-text-secondary' => 'body',
            '--color-divider' => 'hairline',
            '--color-divider-soft' => 'hairline-soft',
            '--color-muted' => 'muted',
            '--color-muted-soft' => 'muted-soft',
            '--color-accent' => 'accent',
            '--color-accent-strong' => 'accent-strong',
            '--color-accent-on-dark' => 'accent-on-dark',
            '--color-accent-wash' => 'accent-wash',
            '--color-canvas-dark' => 'canvas-dark',
            '--color-surface-dark' => 'surface-dark',
            '--color-surface-dark-elevated' => 'surface-dark-elevated',
            '--color-divider-dark' => 'hairline-dark',
            '--color-on-dark' => 'on-dark',
            '--color-on-dark-muted' => 'on-dark-muted',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function palette(): array
    {
        preg_match_all(
            '/^\s{2}([a-z-]+):\s*"(#[0-9A-Fa-f]{6})"$/m',
            (string) file_get_contents(base_path('DESIGN.md')),
            $matches,
            PREG_SET_ORDER,
        );

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => strtoupper($m[2])])->all();
    }

    #[Test]
    public function the_stylesheet_agrees_with_the_document_that_governs_it(): void
    {
        $palette = $this->palette();
        $stylesheet = $this->stylesheet();

        // If DESIGN.md's palette block ever stops parsing, every comparison
        // below would silently pass against nothing.
        $this->assertGreaterThan(20, count($palette), 'The palette in DESIGN.md no longer parses.');

        $disagreements = [];

        foreach (static::tokens() as $property => $name) {
            $this->assertArrayHasKey($name, $palette, "DESIGN.md no longer names '{$name}'.");

            preg_match("/{$property}:\s*(#[0-9A-Fa-f]{6});/", $stylesheet, $found);

            $declared = isset($found[1]) ? strtoupper($found[1]) : 'undeclared';

            if ($declared !== $palette[$name]) {
                $disagreements[$property] = "{$declared} (DESIGN.md '{$name}' is {$palette[$name]})";
            }
        }

        $this->assertSame([], $disagreements, 'The stylesheet drifted from DESIGN.md.');
    }

    /**
     * Three families, each doing one job. A face that is declared but never
     * loaded silently falls through to a system font, which is the kind of
     * regression nobody notices until a screenshot.
     */
    #[Test]
    public function every_declared_face_is_actually_loaded(): void
    {
        $stylesheet = $this->stylesheet();
        $document = (string) file_get_contents(base_path('resources/views/app.blade.php'));

        foreach (['Literata' => '--font-serif', 'Satoshi' => '--font-sans', 'IBM Plex Mono' => '--font-mono'] as $family => $property) {
            $this->assertStringContainsString("{$property}: '{$family}'", $stylesheet, "{$family} is not the {$property} face.");
            $this->assertStringContainsString(str_replace(' ', '+', $family), $document, "{$family} is declared but never loaded.");
        }
    }

    /**
     * Eyebrow labels are the most repeated element in the app. Hand-rolling
     * them is how a monospace label quietly becomes a sans one on a single
     * page, so the utilities that spell one out are barred.
     */
    #[Test]
    public function labels_come_from_the_class_and_not_from_six_utilities(): void
    {
        $handRolled = [];

        foreach (static::components() as $path) {
            $source = (string) file_get_contents($path);

            if (preg_match('/text-xs\s+uppercase\s+tracking-widest/', $source) === 1) {
                $handRolled[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame([], $handRolled, 'These files spell out an eyebrow instead of using .type-eyebrow.');
    }

    /**
     * The dark surface is reserved for the coach and the brain: the two
     * places where the student is talking rather than reading. Both halves
     * are asserted — that the room is inhabited, which is the state phase 3's
     * prerequisites existed to close, and that nobody else moved in.
     */
    #[Test]
    public function the_dark_room_is_inhabited(): void
    {
        $this->assertStringContainsString('.surface-dark {', $this->stylesheet());

        $this->assertStringContainsString(
            'surface-dark',
            (string) file_get_contents(base_path('resources/js/Layouts/AppLayout.tsx')),
            'The layout no longer knows how to paint a dark page.',
        );

        foreach (['Coach/Index.tsx' => 'The coach', 'Brain/Index.tsx' => 'The brain'] as $page => $who) {
            $this->assertStringContainsString(
                'surface="dark"',
                (string) file_get_contents(base_path('resources/js/Pages/'.$page)),
                $who.' left the dark room.',
            );
        }

        /*
         * A reservation that anyone may take up is not a reservation. The
         * dark surface means "you are talking, not reading", and it stops
         * meaning that the moment a third page borrows it for atmosphere.
         */
        $squatters = [];

        foreach (static::components() as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if (str_contains($relative, 'Pages/Coach/') || str_contains($relative, 'Pages/Brain/')) {
                continue;
            }

            if (str_contains((string) file_get_contents($path), 'surface="dark"')) {
                $squatters[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $squatters,
            'The dark surface is reserved for the coach and the brain. These pages took it anyway.',
        );
    }

    /**
     * One accent per view is the whole discipline: if two things are indigo,
     * neither one is the next move. Keeping the filled treatment inside a
     * single class is what makes that countable rather than a matter of taste
     * — and it is the same rule that forbids colour-coding a category, a
     * confidence level or a readiness state.
     */
    #[Test]
    public function the_accent_is_filled_in_exactly_one_place(): void
    {
        $sprayed = [];

        foreach (static::components() as $path) {
            $source = (string) file_get_contents($path);

            if (str_contains($source, 'bg-[var(--color-accent)]')) {
                $sprayed[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $sprayed,
            'These files fill with the accent directly. Use .action-primary, so there is one of it.',
        );

        $this->assertStringContainsString('.action-primary {', $this->stylesheet());
    }

    /**
     * Every layout expresses colour through the tokens, including the ones
     * the student never sees.
     *
     * The staff and admin shells were carried forward from before `DESIGN.md`
     * existed and were noted as debt on that assumption. They are in fact
     * clean — so this pins it rather than leaving the next person to find out
     * by reading three files. A layout is the highest-leverage place for a
     * stray palette utility, because every page underneath it inherits the
     * inconsistency.
     */
    #[Test]
    public function every_layout_takes_its_colour_from_the_tokens(): void
    {
        $offenders = [];

        foreach (glob(base_path('resources/js/Layouts/*.tsx')) as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all(
                '/\b(?:text|bg|border|ring|from|to|via|divide)-(?:stone|gray|slate|zinc|neutral|indigo|blue|red|green|amber|emerald)-\d+/',
                $source,
                $matches,
            );

            preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $source, $hexes);

            $found = array_merge($matches[0], $hexes[0]);

            if ($found !== []) {
                $offenders[basename($path)] = array_values(array_unique($found));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A layout is naming colours directly. Use the tokens, so a change to DESIGN.md reaches every page under it.',
        );
    }

    /**
     * @return list<string>
     */
    protected static function components(): array
    {
        $files = [];
        $directory = new \RecursiveDirectoryIterator(base_path('resources/js'));

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'tsx') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Mobile token parity.
     *
     * `DESIGN.md` listed this as a known gap and predicted its consequence:
     * "mobile/constants/theme.ts carries its own values and will drift until
     * reconciled." It had. Light `accent` was #A8A29E — the warm stone the
     * document says indigo replaced; the dark canvas was #0F1216, a cool
     * blue-black the document explicitly rules out; and dark `accent` was
     * #94A3B8, a second accent hue where exactly one is allowed.
     *
     * A gap named in prose drifts. A gap named in a test cannot, which is why
     * this is here rather than in the Known Gaps list.
     *
     * @return array<string, array{string, string}>
     */
    public static function mobileTokens(): array
    {
        return [
            'light background' => ['lightColors.background', 'canvas'],
            'light surface soft' => ['lightColors.surfaceSoft', 'surface-soft'],
            'light surface card' => ['lightColors.surfaceCard', 'surface-card'],
            'light surface strong' => ['lightColors.surfaceStrong', 'surface-strong'],
            'light text' => ['lightColors.text', 'ink'],
            'light strong text' => ['lightColors.textStrong', 'body-strong'],
            'light secondary text' => ['lightColors.textSecondary', 'body'],
            'light muted' => ['lightColors.muted', 'muted'],
            'light muted soft' => ['lightColors.mutedSoft', 'muted-soft'],
            'light accent' => ['lightColors.accent', 'accent'],
            'light accent strong' => ['lightColors.accentStrong', 'accent-strong'],
            'light accent wash' => ['lightColors.accentWash', 'accent-wash'],
            'light divider' => ['lightColors.divider', 'hairline'],
            'light divider soft' => ['lightColors.dividerSoft', 'hairline-soft'],
            'light error' => ['lightColors.error', 'error'],
            'dark background' => ['darkColors.background', 'canvas-dark'],
            'dark surface soft' => ['darkColors.surfaceSoft', 'surface-dark'],
            'dark surface card' => ['darkColors.surfaceCard', 'surface-dark'],
            'dark surface strong' => ['darkColors.surfaceStrong', 'surface-dark-elevated'],
            'dark text' => ['darkColors.text', 'on-dark'],
            'dark strong text' => ['darkColors.textStrong', 'on-dark'],
            'dark secondary text' => ['darkColors.textSecondary', 'on-dark-muted'],
            'dark muted' => ['darkColors.muted', 'on-dark-muted'],
            'dark accent' => ['darkColors.accent', 'accent-on-dark'],
            'dark accent strong' => ['darkColors.accentStrong', 'accent-on-dark'],
            'dark divider' => ['darkColors.divider', 'hairline-dark'],
            'dark divider soft' => ['darkColors.dividerSoft', 'hairline-dark'],
        ];
    }

    /**
     * The spacing scale, read out of DESIGN.md's `spacing:` block.
     *
     * @return array<string, int>
     */
    protected function spacingScale(): array
    {
        $document = (string) file_get_contents(base_path('DESIGN.md'));

        if (preg_match('/^spacing:\n((?:\s{2}[a-z]+:\s*\d+px\n?)+)/m', $document, $block) !== 1) {
            return [];
        }

        preg_match_all('/^\s{2}([a-z]+):\s*(\d+)px$/m', $block[1], $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => (int) $m[2]])->all();
    }

    /**
     * The palette test above guards colour. This guards the other half of the
     * same claim, and the half that actually drifted: mobile carried its own
     * spacing scale, so `section` was 64 where the document says 96, and `xxs`
     * and `hero` did not exist at all. Nothing caught it, because colour was
     * the only thing anyone thought to assert.
     */
    #[Test]
    public function the_mobile_spacing_scale_agrees_with_the_document(): void
    {
        $expected = $this->spacingScale();

        $this->assertGreaterThan(7, count($expected), 'The spacing block in DESIGN.md no longer parses.');

        $theme = (string) file_get_contents(base_path('mobile/constants/theme.ts'));

        $this->assertSame(
            1,
            preg_match('/export const spacing = \{(.+?)\};/s', $theme, $block),
            'mobile/constants/theme.ts no longer exports spacing.',
        );

        $drifted = [];

        foreach ($expected as $token => $value) {
            if (preg_match('/\b'.preg_quote($token, '/').':\s*(\d+)/', $block[1], $found) !== 1) {
                $drifted[$token] = 'missing';

                continue;
            }

            if ((int) $found[1] !== $value) {
                $drifted[$token] = "{$found[1]} (DESIGN.md says {$value})";
            }
        }

        $this->assertSame([], $drifted, 'The mobile spacing scale has drifted from DESIGN.md.');
    }

    #[Test]
    #[DataProvider('mobileTokens')]
    public function the_mobile_palette_agrees_with_the_same_document(string $path, string $token): void
    {
        [$object, $key] = explode('.', $path);

        $theme = (string) file_get_contents(base_path('mobile/constants/theme.ts'));

        $this->assertSame(
            1,
            preg_match(
                '/export const '.preg_quote($object, '/').' = \{(.+?)\};/s',
                $theme,
                $block,
            ),
            "mobile/constants/theme.ts no longer exports {$object}.",
        );

        $this->assertSame(
            1,
            preg_match('/\b'.preg_quote($key, '/').":\s*'(#[0-9A-Fa-f]{6})'/", $block[1], $value),
            "{$object} no longer defines {$key}.",
        );

        $this->assertSame(
            $this->palette()[$token],
            strtoupper($value[1]),
            "Mobile {$path} has drifted from DESIGN.md's {$token}.",
        );
    }

    /**
     * The document allows exactly one accent hue. Mobile had introduced a
     * second by picking its own dark-mode accent, which is the specific way
     * this rule gets broken: not by adding a colour to the palette, but by a
     * second surface quietly choosing its own.
     */
    #[Test]
    public function mobile_did_not_invent_a_second_accent(): void
    {
        $theme = (string) file_get_contents(base_path('mobile/constants/theme.ts'));

        preg_match_all("/accent:\s*'(#[0-9A-Fa-f]{6})'/", $theme, $accents);

        $palette = $this->palette();

        $this->assertNotEmpty($accents[1]);

        foreach ($accents[1] as $accent) {
            $this->assertContains(
                strtoupper($accent),
                [$palette['accent'], $palette['accent-strong'], $palette['accent-on-dark']],
                "Mobile uses {$accent} as an accent, which is not the one accent hue.",
            );
        }
    }
}
