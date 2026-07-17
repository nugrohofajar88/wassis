<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'relationship_type',
        'notes',
        'avatar',
        'is_active',
        'ai_enabled',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'ai_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function styleProfile(): HasOne
    {
        return $this->hasOne(StyleProfile::class);
    }
}
