<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class VocationalCategory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'ministry_connection',
        'career_pathways',
        'sort_order',
        'core_function',
        'summary_sentence',
        'signal_fingerprint',
        'distortions',
        'adjacent_categories',
        'taxonomy_profile',
    ];

    protected function casts(): array
    {
        return [
            'career_pathways' => 'array',
            'signal_fingerprint' => 'array',
            'distortions' => 'array',
            'adjacent_categories' => 'array',
            'taxonomy_profile' => 'array',
        ];
    }

    /**
     * The categories the engine most often confuses with this one.
     *
     * Resolved by slug rather than by foreign key: adjacency is taxonomy
     * content owned by the blueprint, not a relational fact about rows.
     *
     * @return Collection<int, self>
     */
    public function adjacentCategories(): Collection
    {
        $slugs = $this->adjacent_categories['slugs'] ?? [];

        if ($slugs === []) {
            return collect();
        }

        return static::whereIn('slug', $slugs)->orderBy('sort_order')->get();
    }

    /**
     * The questions the engine asks itself when this category competes with a
     * neighbour. Internal reasoning aids — never surfaced to a student.
     *
     * @return array<int, string>
     */
    public function differentiatingQuestions(): array
    {
        return $this->adjacent_categories['differentiating_questions'] ?? [];
    }

    /**
     * Literal phrases drawn from the blueprint that indicate this pathway.
     *
     * @return array<int, string>
     */
    public function literalSignalPhrases(): array
    {
        return $this->signal_fingerprint['literal_phrases'] ?? [];
    }

    /**
     * True when the taxonomy content required to govern the AI is present.
     * A category without this is name-and-description only, which the
     * blueprint treats as insufficient to constrain interpretation.
     */
    public function hasGoverningTaxonomy(): bool
    {
        return filled($this->core_function)
            && filled($this->summary_sentence)
            && filled($this->signal_fingerprint)
            && filled($this->distortions)
            && filled($this->taxonomy_profile);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function taggedCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_vocational_category')
            ->withPivot('relevance_weight')
            ->withTimestamps();
    }
}
