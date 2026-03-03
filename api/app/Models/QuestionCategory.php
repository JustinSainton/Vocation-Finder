<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionCategory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'theological_basis',
        'what_it_reveals',
        'sort_order',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'category_id')->orderBy('sort_order');
    }
}
