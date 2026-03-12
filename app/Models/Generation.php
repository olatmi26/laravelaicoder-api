<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    protected $fillable = [
        'project_id','user_id','prompt','status','ai_provider','ai_model',
        'used_byok','tokens_used','cost_usd','agent_pipeline','started_at','finished_at',
    ];
    protected $casts = [
        'agent_pipeline' => 'array',
        'used_byok'      => 'boolean',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function agentJobs(): HasMany { return $this->hasMany(AgentJob::class)->orderBy('sequence'); }
    public function files(): HasMany     { return $this->hasMany(ProjectFile::class); }

    public function isComplete(): bool { return $this->status === 'done'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }
    public function isRunning(): bool  { return $this->status === 'running'; }
}
