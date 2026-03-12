<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UiUpload extends Model
{
    protected $fillable = [
        'project_id', 'user_id', 'original_filename', 'storage_path', 'status',
        'design_tokens', 'component_map', 'components_found', 'pages_found',
        'integration_plan', 'analyzed_at',
    ];

    protected $casts = [
        'design_tokens' => 'array',
        'component_map' => 'array',
        'analyzed_at'   => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
