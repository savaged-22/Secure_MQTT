<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsedNonce extends Model
{
    protected $primaryKey = 'nonce';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['nonce', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
