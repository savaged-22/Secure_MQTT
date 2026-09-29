<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    protected $fillable = [
        'user_id', 'turnstile_id', 'correlation_id',
        'granted', 'reason', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turnstile(): BelongsTo
    {
        return $this->belongsTo(Turnstile::class);
    }
}
