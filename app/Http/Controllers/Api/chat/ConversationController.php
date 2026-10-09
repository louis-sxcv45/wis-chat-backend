<?php

namespace App\Http\Controllers\Api\chat;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{
    //
    public function getConversation(Request $request)
    {
        $user = $request->user();

        $conversation = $user->conversations()->with([
            'users',
            'latestMessage.sender'
        ])->orderByDesc('last_message_at')->get();

        $data = $conversation->map(function ($conversation) use ($user) {

            if ($conversation->type === 'personal') {
                $otherUser = $conversation->users->firstWhere('id_user', '!=', $user->id_user);

                return [
                    'id_conversation' => $conversation->id_conversation,
                    'type' => $conversation->type,
                    'name' => $otherUser?->name,
                    'photo_profile' => $otherUser?->photo_profile_url,
                    'last_message' => $conversation->latestMessage?->message,
                    'last_message_sender' => $conversation->latestMessage?->sender?->name,
                    'last_message_at' => $conversation->latestMessage?->sent_at,
                ];
            }

            return [
                'id_conversation' => $conversation->id_conversation,
                'type' => $conversation->type,
                'name_group' => $conversation->name_group,
                'photo_group' => $conversation->photo_group_url,
                'last_message' => $conversation->latestMessage?->message,
                'last_message_sender' => $conversation->latestMessage?->sender?->name,
                'last_message_at' => $conversation->latestMessage?->sent_at,
            ];
        });

        if ($data->isEmpty()) {
            return ResponseFormatter::error(
                [
                    'message' => 'Chat is empty',
                    'data' => []
                ], 404
            );
        }

        return ResponseFormatter::success(
            [
                'message' => 'Get conversation success',
                'data' => $data,
            ], 200
        );
    }

    public function directMessage(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'id_user' => 'required|exists:users,id_user'
        ]);

        $otherUserId = $request->id_user;

        /// for checking chat with himself
        if ($user->id_user === $otherUserId) {
            return ResponseFormatter::error(
                [
                    'message' => 'You cannot chat with yourself',
                ], 422
            );
        }

        /// Check if a personal conversation already exists between the two users
        $conversation = Conversation::where('type', 'personal')
            ->whereHas('users', fn($query) => $query->where('users.id_user', $user->id_user))
            ->whereHas('users', fn($query) => $query->where('users.id_user', $otherUserId))
            ->first()
            ?? DB::transaction(function () use ($user, $otherUserId) {
                    $conversation = Conversation::create([
                        'type' => 'personal'
                    ]);
                    $conversation->users()->attach([
                        $user->id_user => ['role' => 'peer'],
                        $otherUserId => ['role' => 'peer']
                    ]);
                    
                    return $conversation;
                });

        $conversation->load('users');

        return ResponseFormatter::success([
            'message' => 'Direct message conversation retrieved successfully',
            'data' => $conversation,
        ], 200);
    }

    public function createGroupChat(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name_group' => 'required|string|max:255',
            'photo_group' => 'image|mimes:jpeg,png,jpg|max:2048',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id_user'
        ]);

        $conversation = DB::transaction(
            function () use ($request, $user) {
                $photoPath = $request->file('photo_group')
                    ? $request->file('photo_group')->store('group_photos', 'public')
                    : null;
                $conversation = Conversation::create([
                    'type' => 'group',
                    'name_group' => $request->name_group,
                    'photo_group' => $photoPath
                ]);

                $conversation->users()->attach(
                    $user->id_user,
                    [
                        'role' => 'admin',
                        'joined_at' => now()
                    ]
                );

                foreach ($request->user_ids as $userId) {
                    if ($userId === $user->id_user) {
                        continue;
                    }

                    $conversation->users()->attach(
                        $userId,
                        [
                            'role' => 'member',
                            'joined_at' => now()
                        ]
                    );
                }

                return $conversation;
            }
        );

        $conversation->load('users');

        return ResponseFormatter::success([
            'message' => 'Group chat created successfully',
            'data' => $conversation,
        ], 201);
    }

    public function getGroupChat(Request $request, $id_conversation)
    {
        $user = $request->user();

        $conversation = Conversation::with('users')
            ->where('id_conversation', $id_conversation)
            ->where('type', 'group')
            ->whereHas('users', fn ($query) => $query->where('users.id_user', $user->id_user))
            ->firstOrFail();

        return ResponseFormatter::success([
            'message' => 'Group chat retrieved successfully',
            'data' => $conversation,
        ], 200);
    }

    public function updateGroupChat(Request $request, $id_conversation)
    {
        $user = $request->user();

        $conversation = Conversation::findOrFail($id_conversation);

        if ($conversation->type !== 'group') {
            return ResponseFormatter::error([
                'message' => 'This conversation is not a group chat',
            ], 400);
        }

        if (! $conversation->users()->where('users.id_user', $user->id_user)->wherePivot('role', 'admin')->exists()) {
            return ResponseFormatter::error([
                'message' => 'Only group admins can update this group',
            ], 403);
        }

        $request->validate([
            'name_group' => 'sometimes|string|max:255',
            'photo_group' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'distinct|exists:users,id_user',
            'admin_ids' => 'sometimes|array',
            'admin_ids.*' => 'distinct|exists:users,id_user'
        ]);

        if ($request->has('name_group')) {
            $conversation->name_group = $request->name_group;
        }

        if ($request->hasFile('photo_group')) {
            $currentPhotoPath = $conversation->getRawOriginal('photo_group');
            if ($currentPhotoPath) {
                Storage::disk('public')->delete($currentPhotoPath);
            }
            $conversation->photo_group = $request->file('photo_group')->store('group_photos', 'public');
        }

        $currentAdminIds = $conversation->users()
            ->wherePivot('role', 'admin')
            ->pluck('users.id_user')
            ->map(fn ($id) => (int) $id)
            ->all();
        $currentMemberIds = $conversation->users()
            ->wherePivot('role', 'member')
            ->pluck('users.id_user')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($request->has('user_ids')) {
            $memberIds = array_values(array_diff(
                array_map('intval', $request->input('user_ids', [])),
                $currentAdminIds
            ));

            $conversation->users()->syncWithoutDetaching(
                collect($memberIds)->mapWithKeys(fn ($userId) => [
                    $userId => ['role' => 'member', 'joined_at' => now()]
                ])->all()
            );
        } else {
            $memberIds = $currentMemberIds;
        }

        if ($request->has('admin_ids')) {
            $requestedAdminIds = array_map('intval', $request->input('admin_ids', []));
            $promotableIds = array_values(array_diff($memberIds, $currentAdminIds));
            $invalidAdminIds = array_diff($requestedAdminIds, $currentAdminIds, $promotableIds);

            if ($invalidAdminIds) {
                return ResponseFormatter::error([
                    'message' => 'Only existing group members can be promoted to admin',
                ], 422);
            }

            foreach (array_intersect($requestedAdminIds, $promotableIds) as $adminId) {
                $conversation->users()->updateExistingPivot($adminId, [
                    'role' => 'admin'
                ]);
            }
        }

        $conversation->save();
        $conversation->load('users');

        return ResponseFormatter::success([
            'message' => 'Group chat updated successfully',
            'data' => $conversation,
        ], 200);
    }

    public function kickGroupMember(Request $request, $id_conversation, $id_user)
    {
        $user = $request->user();

        $conversation = Conversation::findOrFail($id_conversation);

        if ($conversation->type !== 'group') {
            return ResponseFormatter::error([
                'message' => 'This conversation is not a group chat',
            ], 422);
        }

        if (! $conversation->users()
            ->where('users.id_user', $user->id_user)
            ->wherePivot('role', 'admin')
            ->exists()
        ) {
            return ResponseFormatter::error([
                'message' => 'Only group admins can kick members',
            ], 403);
        }

        $member = $conversation->users()
            ->where('users.id_user', $id_user)
            ->wherePivot('role', 'member')
            ->first();

        if (! $member) {
            return ResponseFormatter::error([
                'message' => 'The selected user is not a member of this group',
            ], 404);
        }

        $conversation->users()->detach($id_user);

        return ResponseFormatter::success([
            'message' => 'Member kicked from group successfully',
        ], 200);
    }

    public function deleteGroupChat(Request $request, $id_conversation)
    {
        $user = $request->user();

        $conversation = Conversation::findOrFail($id_conversation);

        if ($conversation->type !== 'group') {
            return ResponseFormatter::error([
                'message' => 'This conversation is not a group chat',
            ], 422);
        }

        if (! $conversation->users()->where('users.id_user', $user->id_user)->wherePivot('role', 'admin')->exists()) {
            return ResponseFormatter::error([
                'message' => 'Only group admins can delete this group',
            ], 403);
        }

        $photoPath = $conversation->getRawOriginal('photo_group');
        $conversation->delete();

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }

        return ResponseFormatter::success([
            'message' => 'Group chat deleted successfully',
        ], 200);
    }

    public function leaveGroupChat(Request $request, $id_conversation)
    {
        $user = $request->user();

        $conversation = Conversation::findOrFail($id_conversation);

        if ($conversation->type !== 'group') {
            return ResponseFormatter::error([
                'message' => 'This conversation is not a group chat',
            ], 422);
        }

        if (! $conversation->users()->where('users.id_user', $user->id_user)->exists()) {
            return ResponseFormatter::error([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $conversation->users()->detach($user->id_user);

        return ResponseFormatter::success([
            'message' => 'You have left the group chat successfully',
        ], 200);
    }
}
