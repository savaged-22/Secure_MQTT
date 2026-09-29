<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turnstile extends Model
{
    protected $fillable = ['building_id', 'code', 'direction', 'status'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
