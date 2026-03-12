<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'flw_subscription_id', 'flw_plan_id',
        'status', 'billing_cycle', 'amount', 'currency',
        'current_period_start', 'current_period_end', 'cancelled_at', 'flw_data',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancelled_at'         => 'datetime',
        'flw_data'             => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->current_period_end?->isFuture();
    }
}
