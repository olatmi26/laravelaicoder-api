<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineHandoff extends Model
{
    protected $fillable = [
        'source_project_id', 'target_project_id', 'type', 'payload', 'status', 'triggered_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'triggered_at' => 'datetime',
    ];

    public function sourceProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'source_project_id');
    }

    public function targetProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'target_project_id');
    }
}
