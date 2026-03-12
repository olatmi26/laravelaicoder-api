<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'key_encrypted', 'model', 'is_active', 'last_used_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // Never expose the encrypted key in API responses
    protected $hidden = ['key_encrypted'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Encryption helpers ──

    public function setKeyAttribute(string $plainKey): void
    {
        $this->attributes['key_encrypted'] = Crypt::encryptString($plainKey);
    }

    public function getDecryptedKey(): string
    {
        return Crypt::decryptString($this->key_encrypted);
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    // Mask key for UI display: sk-ant-...abc123 → sk-ant-...••••••
    public function getMaskedKeyAttribute(): string
    {
        try {
            $key = $this->getDecryptedKey();
            return substr($key, 0, 10).'••••••••'.substr($key, -6);
        } catch (\Exception $e) {
            return '••••••••••••••••';
        }
    }
}
