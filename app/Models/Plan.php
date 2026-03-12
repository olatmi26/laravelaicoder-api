<?php
// =====================================================
// Plan.php
// =====================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'price_monthly', 'price_yearly', 'limits', 'is_active'];
    protected $casts    = ['limits' => 'array', 'is_active' => 'boolean'];

    public function users(): HasMany   { return $this->hasMany(User::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }

    public function getMonthlyPriceFormattedAttribute(): string
    {
        return $this->price_monthly === 0 ? 'Free' : '$'.number_format($this->price_monthly / 100, 0);
    }
}
