<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'plan_id',
        'avatar', 'flw_customer_id', 'trial_ends_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'trial_ends_at'     => 'datetime',
        'password'          => 'hashed',
    ];

    // ── Relationships ──

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    // ── Plan Helpers ──

    public function getPlan(): Plan
    {
        return $this->plan ?? Plan::where('slug', 'starter')->first();
    }

    public function planLimits(): array
    {
        return $this->getPlan()->limits ?? [];
    }

    public function canCreateProject(): bool
    {
        $limit = $this->planLimits()['projects'] ?? 1;
        if ($limit === -1) return true;
        return $this->projects()->count() < $limit;
    }

    public function canGenerate(): bool
    {
        $limit = $this->planLimits()['generations'] ?? 100;
        if ($limit === -1) return true;

        $used = $this->generations()
            ->whereMonth('created_at', now()->month)
            ->count();

        return $used < $limit;
    }

    public function canUseMobile(): bool
    {
        return $this->planLimits()['mobile'] ?? false;
    }

    public function canUseCustomUI(): bool
    {
        return $this->planLimits()['custom_ui'] ?? false;
    }

    public function canUseBYOK(): bool
    {
        return $this->planLimits()['byok'] ?? false;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    // ── API Key Helpers ──

    public function getApiKeyForProvider(string $provider): ?ApiKey
    {
        return $this->apiKeys()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}
