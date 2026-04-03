<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DashboardSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'metric_key',
        'value',
        'snapshot_date',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'snapshot_date' => 'date',
            'computed_at' => 'datetime',
        ];
    }

    public static function latest(string $metricKey): ?self
    {
        return static::where('metric_key', $metricKey)
            ->orderByDesc('snapshot_date')
            ->first();
    }

    public static function record(string $metricKey, array $value): self
    {
        return static::updateOrCreate(
            ['metric_key' => $metricKey, 'snapshot_date' => now()->toDateString()],
            ['value' => $value, 'computed_at' => now()]
        );
    }
}
