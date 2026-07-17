<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StyleProfile extends Model
{
    protected $fillable = [
        'user_id',
        'contact_id',
        'formality_level',
        'preferred_tone',
        'uses_emoji',
        'typical_language',
        'summary',
        'last_analyzed_at',
    ];

    protected $casts = [
        'formality_level'  => 'integer',
        'uses_emoji'       => 'boolean',
        'last_analyzed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
