<?php

namespace App\Http\Controllers\Api\user;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\UserModel\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ResponseFormatter::error([
                'message' => 'Email not registered',
                'data' => []
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return ResponseFormatter::error([
                'message' => 'Invalid Password',
                'data' => []
            ], 401);
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return ResponseFormatter::success([
            'message' => 'User Logged In',
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => $user
        ], 200);
    }

    public function register(Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:255', 'unique:users,phone_number'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'username' => $request->username,
            'password'=> Hash::make($request->password)
        ]);

        $user = User::where('email', $request->email)->first();

        $token = $user->createToken('authToken')->plainTextToken;

        return ResponseFormatter::success([
            'message' => 'User Registered',
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => $user
        ], 201);
    }
}
