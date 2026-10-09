<?php

use App\Http\Controllers\Api\chat\ConversationController;
use App\Http\Controllers\Api\chat\MessageController;
use App\Http\Controllers\Api\user\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);


Route::middleware('auth:sanctum')->group(function(){
    ///conversation routes
    Route::get('conversations', [ConversationController::class, 'getConversation']);
    Route::post('personal-chat', [ConversationController::class, 'directMessage']);
    Route::post('create-group-chat', [ConversationController::class, 'createGroupChat']);
    Route::get('group-chats/{id_conversation}', [ConversationController::class, 'getGroupChat']);
    Route::put('group-chats/{id_conversation}', [ConversationController::class, 'updateGroupChat']);
    Route::delete('group-chats/{id_conversation}/members/{id_user}', [ConversationController::class, 'kickGroupMember']);
    Route::delete('group-chats/{id_conversation}', [ConversationController::class, 'deleteGroupChat']);
    Route::delete('group-chats/{id_conversation}/leave', [ConversationController::class, 'leaveGroupChat']);


    ///message routes
    Route::get('message/{conversationId}', [MessageController::class, 'getMessage']);
    Route::post('message/{conversationId}', [MessageController::class, 'sendMessage']);
    Route::put('message/{messageId}', [MessageController::class, 'updateMessage']);
    Route::delete('message/{messageId}', [MessageController::class, 'deleteMessage']);
});