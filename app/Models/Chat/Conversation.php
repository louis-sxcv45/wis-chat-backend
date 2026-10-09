<?php

namespace App\Models\Chat;

use App\Models\Chat\Message;
use App\Models\UserModel\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(
    [
        'type',
        'name_group',
        'photo_group',
    ]

)]

class Conversation extends Model
{
    use HasFactory, Notifiable;

    protected $table = 'conversation';

    protected $primaryKey = 'id_conversation';

    public const UPDATED_AT = null;

    protected $appends = [
        'photo_group_url',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function photoGroupUrl(): Attribute {
        return Attribute::get(
            function() {
                return $this->photo_group
                    ? Storage::disk('public')->url($this->photo_group)
                    : 'https://ui-avatars.com/api/?name=' . urlencode($this->name_group ?? 'Group');
            }
        );
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_user',
            'id_conversation',
            'id_user',
        )->withPivot([
            'role',
            'joined_at',
            'last_seen_at'
        ]) -> withTimestamps();
    }

    public function messages() {
        return $this->hasMany(Message::class, 'id_conversation', 'id_conversation');
    }

    public function latestMessage() {
        return $this->hasOne(Message::class, 'id_conversation', 'id_conversation')->latestOfMany('sent_at');
    }
}
