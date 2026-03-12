<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'type', 'directory',
        'status', 'progress', 'template', 'php_version', 'node_version',
        'dart_version', 'mobile_framework', 'db_driver', 'port',
        'description', 'packages', 'custom_ui_path', 'generation_count',
    ];

    protected $casts = [
        'packages'  => 'array',
        'progress'  => 'integer',
        'port'      => 'integer',
    ];

    // Project type constants
    const TYPES = [
        'laravel-web'  => 'Laravel Web App',
        'laravel-api'  => 'Laravel API Backend',
        'react-native' => 'React Native App',
        'flutter'      => 'Flutter App',
        'react-spa'    => 'React SPA',
        'vue-spa'      => 'Vue SPA',
        'admin-panel'  => 'Admin Dashboard',
    ];

    public function workspace(): BelongsTo  { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function files(): HasMany        { return $this->hasMany(ProjectFile::class); }
    public function generations(): HasMany  { return $this->hasMany(Generation::class); }
    public function plugins(): HasMany      { return $this->hasMany(Plugin::class); }
    public function uiUploads(): HasMany    { return $this->hasMany(UiUpload::class); }

    public function outgoingHandoffs(): HasMany
    {
        return $this->hasMany(PipelineHandoff::class, 'source_project_id');
    }

    public function incomingHandoffs(): HasMany
    {
        return $this->hasMany(PipelineHandoff::class, 'target_project_id');
    }

    // Helper: is this a mobile project?
    public function isMobile(): bool
    {
        return in_array($this->type, ['react-native', 'flutter']);
    }

    // Helper: is this a Laravel project?
    public function isLaravel(): bool
    {
        return in_array($this->type, ['laravel-web', 'laravel-api', 'admin-panel']);
    }

    // Get the default agent pipeline for this project type
    public function getDefaultAgentPipeline(): array
    {
        return config('agents.pipelines.'.$this->type, ['architect', 'laravel', 'qa']);
    }

    // Get the label for the project type
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
