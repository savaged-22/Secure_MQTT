<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = ['code', 'name'];

    public function turnstiles(): HasMany
    {
        return $this->hasMany(Turnstile::class);
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(AccessRule::class);
    }
}
