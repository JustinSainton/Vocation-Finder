<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
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
        'model_version',
        'prompt_version',
        'taxonomy_version',
        'primary_domain',
        'mode_of_work',
        'secondary_orientation',
        'category_scores',
        'confidence_level',
        'confidence_rationale',
        'missing_evidence',
        'evidence_gap',
        'ministry_integration',
    ];

    protected function casts(): array
    {
        return [
            'primary_pathways' => 'array',
            'next_steps' => 'array',
            'ai_analysis_raw' => 'array',
            'category_scores' => 'array',
            'missing_evidence' => 'array',
            'evidence_gap' => 'array',
            'confidence_level' => ConfidenceLevel::class,
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

    /**
     * Whether the result must run in the blueprint's low-confidence mode:
     * no forced conclusion, and a testable next step instead.
     */
    public function isLowConfidence(): bool
    {
        return ($this->confidence_level ?? ConfidenceLevel::InsufficientEvidence)->isLowConfidence();
    }

    /**
     * The derived confidence for a single category, by name.
     */
    public function confidenceFor(string $category): ?ConfidenceLevel
    {
        foreach ($this->category_scores ?? [] as $row) {
            if (($row['category'] ?? null) === $category && isset($row['confidence'])) {
                return ConfidenceLevel::tryFrom($row['confidence']);
            }
        }

        return null;
    }
}
