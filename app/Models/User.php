<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'fcm_token', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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

    public function styleProfiles(): HasMany
    {
        return $this->hasMany(StyleProfile::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /**
     * Get a specific setting value by key.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    protected static function booted(): void
    {
        // Delete dependents explicitly by user_id before the user itself is removed.
        // memories/events/messages/style_profiles are each reachable from `users` both
        // directly (user_id) and indirectly (via contacts), so a single DB-level cascading
        // DELETE hits MySQL's "diamond" cascade limitation (error 1452). Deleting them here
        // first means the DB cascades still defined on these tables become no-op safety nets.
        static::deleting(function (User $user) {
            $user->tokens()->delete();
            $user->messages()->delete();
            $user->memories()->delete();
            $user->events()->delete();
            $user->styleProfiles()->delete();
            $user->settings()->delete();
            $user->contacts()->delete();
        });
    }
}
