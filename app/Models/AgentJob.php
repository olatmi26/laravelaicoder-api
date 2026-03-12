<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentJob extends Model
{
    protected $fillable = [
        'generation_id', 'agent_id', 'sequence', 'status',
        'output_text', 'generated_files', 'tokens_input', 'tokens_output',
        'cost_usd', 'error_message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'generated_files' => 'array',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
    ];

    // Agent ID constants — these match config/agents.php keys
    const ARCHITECT  = 'architect';
    const LARAVEL    = 'laravel';
    const MOBILE     = 'mobile';
    const FRONTEND   = 'frontend';
    const UIUX       = 'uiux';
    const QA         = 'qa';
    const REVIEWER   = 'reviewer';
    const DEVOPS     = 'devops';

    public function generation(): BelongsTo { return $this->belongsTo(Generation::class); }

    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) return null;
        return $this->started_at->diffInSeconds($this->finished_at);
    }
}
