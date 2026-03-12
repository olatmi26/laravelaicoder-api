<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id', 'generation_id', 'path', 'content', 'language', 'status', 'last_generated_at',
    ];

    protected $casts = [
        'last_generated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function getLanguageFromPath(): string
    {
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        return match($ext) {
            'php'         => 'php',
            'js', 'jsx'   => 'javascript',
            'ts', 'tsx'   => 'typescript',
            'dart'        => 'dart',
            'json'        => 'json',
            'yaml', 'yml' => 'yaml',
            'css'         => 'css',
            'scss'        => 'scss',
            'md'          => 'markdown',
            'blade'       => 'blade',
            default       => 'plaintext',
        };
    }
}
