<?php

namespace App\Models\UserModel;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'username',
    'phone_number',
    'status',
    'photo_profile',
    'password'
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id_user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'photo_profile_url',
    ];

    public function photoProfileUrl(): Attribute {
        return Attribute::get(
            function() {
                return $this->photo_profile 
                ? Storage::disk('public')->url($this->photo_profile)
                : 'https://ui-avatars.com/api/?name=' . urlencode($this->name);
            }
        );
    }

    public function isAdmin(): bool {
        return $this->role === 'admin';
    }

    public function conversations() {
        return $this->belongsToMany(
            Conversation::class,
            'conversation_user',
            'id_user',
            'id_conversation'
        );
    }

    public function messages() {
        return $this->hasMany(
            Message::class,
            'sender_id',
            'id_user'
        );
    }
}
