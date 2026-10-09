<?php

namespace App\Models\Chat;

use App\Models\UserModel\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'id_conversation',
    'sender_id',
    'type_message',
    'message',
    'attachment',
    'reply_to_id',
    'sent_at',
])]

class Message extends Model
{
    use HasFactory, Notifiable;

    protected $table = 'message';

    protected $primaryKey = 'id_message';

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $appends = [
        'attachment_url',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    protected function attachmentUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->attachment
                ? Storage::disk('public')->url($this->attachment)
                : null
        );
    }

    public function conversation() {
        return $this->belongsTo(Conversation::class, 'id_conversation', 'id_conversation');
    }

    public function sender() {
        return $this->belongsTo(User::class, 'sender_id', 'id_user');
    }

    public function replyTo() {
        return $this->belongsTo(Message::class, 'reply_to_id', 'id_message');
    }
}
