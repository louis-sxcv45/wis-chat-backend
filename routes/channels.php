<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return $user->conversations()
    -> where('conversation.id_conversation', $conversationId)
    -> exists();
});
