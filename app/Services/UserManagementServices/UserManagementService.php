<?php

namespace App\Services\UserManagementServices;

use App\Models\User;
use App\Services\FileStorage;
use Illuminate\Support\Facades\Auth;

class UserManagementService
{
    public function register(array $data)
    {
        $avatarPath = null;

        if (isset($data['avatar'])) {
            $avatarPath = FileStorage::storeFile(
                $data['avatar'],
                'avatars',
                'img'
            );
        }

        $user = User::create([
            'name'     => $data['name'],
            'phone'    => $data['phone'],
            'gender'    => $data['gender'],
            'city'    => $data['city'],
            'password' => bcrypt($data['password']),
            'avatar' => $avatarPath,
            'v_location' => $data['v_location'],
            'h_location' => $data['h_location'],
            'is_admin' => 0
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function login(array $data)
    {
        if (!Auth::attempt(['phone' => $data['phone'], 'password' => $data['password']])) {
            return ['error' => 'كلمة المرور أو الرقم غير صحيحين'];
        }

        $user = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function logout($request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return ['message' => 'Logged out'];
    }
}
