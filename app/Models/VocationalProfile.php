<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocationalProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_id',
        'opening_synthesis',
        'vocational_orientation',
        'primary_pathways',
        'specific_considerations',
        'next_steps',
        'ai_analysis_raw',
        'primary_domain',
        'mode_of_work',
        'secondary_orientation',
        'category_scores',
        'ministry_integration',
    ];

    protected function casts(): array
    {
        return [
            'primary_pathways' => 'array',
            'next_steps' => 'array',
            'ai_analysis_raw' => 'array',
            'category_scores' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Returns the top vocational categories matched by score, enriched with user-facing blurbs.
     *
     * @return array<int, array{name: string, description: string, ministry_connection: string, career_pathways: array<int, string>}>
     */
    public function matchedPathwayBlurbs(int $limit = 3): array
    {
        $scores = $this->category_scores ?? [];
        if (empty($scores)) {
            return [];
        }

        usort($scores, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $topNames = array_column(array_slice($scores, 0, $limit), 'category');

        $categories = VocationalCategory::whereIn('name', $topNames)
            ->get()
            ->keyBy('name');

        return collect($topNames)
            ->map(function (string $name) use ($categories) {
                $category = $categories->get($name);
                if (! $category) {
                    return null;
                }

                return [
                    'name' => $category->name,
                    'description' => $category->description,
                    'ministry_connection' => $category->ministry_connection,
                    'career_pathways' => $category->career_pathways ?? [],
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
