<?php

namespace App\Http\Controllers\Api\chat;

use App\Events\MessageSent;
use App\Events\MessageDeleted;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function getMessage(Request $request, $conversationId) {
        $user = $request->user();

        $conversation = $user->conversations()
        ->where('conversation.id_conversation', $conversationId)
        ->firstOrFail();

        $messages = Message::with('sender')
        ->where('id_conversation', $conversation->id_conversation)
        ->orderBy('sent_at', 'asc')
        ->get();

        if($messages->isEmpty()) {
            return ResponseFormatter::error([
                'message' => 'No data messages found for this conversation',
                'data' => []
            ], 404);
        }

        return ResponseFormatter::success([
            'message' => 'Messages retrieved successfully',
            'data' => $messages
        ], 200);
    }

    public function sendMessage(Request $request, $conversationId) {
        $user = $request->user();

        $conversation = $user->conversations()
        ->where('conversation.id_conversation', $conversationId)
        ->firstOrFail();

        $validated = $request->validate([
            'type_message' => 'required|in:text,image,video,audio,file',
            'message' => 'required_if:type_message,text|nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'reply_to_id' => 'nullable|exists:message,id_message',
        ]);

        $type = $validated['type_message'];
        $attachment = $request->file('attachment');

        if ($type === 'text' && $attachment) {
            throw ValidationException::withMessages([
                'attachment' => 'Text messages cannot contain an attachment.',
            ]);
        }

        if ($type !== 'text' && ! $attachment) {
            throw ValidationException::withMessages([
                'attachment' => 'An attachment is required for this message type.',
            ]);
        }

        if ($attachment) {
            $allowedMimeTypes = [
                'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                'video' => ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'],
                'audio' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/ogg'],
                'file' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                    'application/x-rar-compressed',
                ],
            ];

            if ($type === 'text' || ! in_array($attachment->getMimeType(), $allowedMimeTypes[$type], true)) {
                throw ValidationException::withMessages([
                    'attachment' => "The attachment does not match the {$type} message type.",
                ]);
            }
        }

        $attachmentPath = $attachment?->store('message_attachments', 'public');

        $message = Message::create([
            'id_conversation' => $conversation->id_conversation,
            'sender_id' => $user->id_user,
            'type_message' => $type,
            'message' => $validated['message'] ?? null,
            'attachment' => $attachmentPath,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
            'sent_at' => now(),
        ]);

        if(!$message) {
            return ResponseFormatter::error(
                [
                    'message' => 'Failed to send message',
                    'data' => []
                ], 500
            );
        }

        $conversation->update(['last_message_at' => now()]);

        $message->load('sender');
        broadcast(new MessageSent($message))->toOthers();

        return ResponseFormatter::success([
            'message' => 'Message sent successfully',
            'data' => $message
        ], 200);
    }

    public function updateMessage(Request $request, $messageId) {
        $user = $request->user();

        $message = Message::where('id_message', $messageId)
        -> where('sender_id', $user->id_user)
        -> firstOrFail();

        if ($message->type_message !== 'text') {
            throw ValidationException::withMessages([
                'message' => 'Only text messages can be updated.',
            ]);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $updated = $message->update([
            'message' => $validated['message'],
            'edited_at' => now()
        ]);

        if (! $updated) {
            return ResponseFormatter::error(
                [
                    'message' => 'Failed to update message',
                    'data' => []
                ], 500
            );
        }

        $message->refresh()->load('sender');
        broadcast(new MessageSent($message))->toOthers();

        return ResponseFormatter::success([
            'message' => 'Message updated successfully',
            'data' => $message
        ], 200);
    }

    public function deleteMessage(Request $request, $messageId) {
        $user = $request->user();

        $message = Message::where('id_message', $messageId)
            ->where('sender_id', $user->id_user)
            ->firstOrFail();

        $conversationId = $message->id_conversation;
        $attachmentPath = $message->attachment;

        $deleted = $message->delete();

        if (! $deleted) {
            return ResponseFormatter::error([
                'message' => 'Failed to delete message',
                'data' => [],
            ], 500);
        }

        if ($attachmentPath) {
            Storage::disk('public')->delete($attachmentPath);
        }

        broadcast(new MessageDeleted(
            $message->id_message,
            $conversationId,
        ))->toOthers();

        return ResponseFormatter::success([
            'message' => 'Message deleted successfully',
            'data' => [
                'id_message' => $message->id_message,
            ],
        ], 200);
    }
}
