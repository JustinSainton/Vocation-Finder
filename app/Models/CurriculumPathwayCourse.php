<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumPathwayCourse extends Model
{
    use HasUuids;

    protected $fillable = [
        'curriculum_pathway_id',
        'course_id',
        'phase',
        'position_in_phase',
        'selection_rationale',
        'enrollment_id',
    ];

    public function pathway(): BelongsTo
    {
        return $this->belongsTo(CurriculumPathway::class, 'curriculum_pathway_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }
}
