<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id','generation_id','path','content','language','status','last_generated_at',
    ];
    protected $casts = ['last_generated_at' => 'datetime'];
    public function project(): BelongsTo    { return $this->belongsTo(Project::class); }
    public function generation(): BelongsTo { return $this->belongsTo(Generation::class); }

    public function getLanguageFromPath(): string
    {
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        return match($ext) {
            'php'        => 'php',
            'js','jsx'   => 'javascript',
            'ts','tsx'   => 'typescript',
            'dart'       => 'dart',
            'blade','php' => 'blade',
            'json'       => 'json',
            'yaml','yml' => 'yaml',
            default      => 'plaintext',
        };
    }
}

class PipelineHandoff extends Model
{
    protected $fillable = [
        'source_project_id','target_project_id','type','payload','status','triggered_at',
    ];
    protected $casts = ['payload' => 'array', 'triggered_at' => 'datetime'];
    public function sourceProject(): BelongsTo { return $this->belongsTo(Project::class,'source_project_id'); }
    public function targetProject(): BelongsTo { return $this->belongsTo(Project::class,'target_project_id'); }
}

class Plugin extends Model
{
    protected $fillable = ['project_id','user_id','name','type','version','status','installed_at'];
    protected $casts    = ['installed_at' => 'datetime'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}

class UiUpload extends Model
{
    protected $fillable = [
        'project_id','user_id','original_filename','storage_path','status',
        'design_tokens','component_map','components_found','pages_found',
        'integration_plan','analyzed_at',
    ];
    protected $casts = [
        'design_tokens' => 'array',
        'component_map' => 'array',
        'analyzed_at'   => 'datetime',
    ];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
