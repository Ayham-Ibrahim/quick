<?php

namespace App\Services\UserManagementServices;

use App\Models\Driver;
use App\Models\Store;
use App\Models\UserManagement\Provider;
use App\Models\UserManagement\User;
use App\Services\FileStorage;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function register(array $data)
    {
        $avatarPath = isset($data['avatar'])
            ? FileStorage::storeFile($data['avatar'], 'avatars', 'img')
            : null;

        $user = User::create([
            'name'        => $data['name'],
            'phone'       => $data['phone'],
            'gender'      => $data['gender'],
            'city'        => $data['city'],
            'password'    => bcrypt($data['password']),
            'avatar'      => $avatarPath,
            'v_location'  => $data['v_location'],
            'h_location'  => $data['h_location'],
            'is_admin'    => 0
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function login(array $credentials)
    {
        $model = match ($credentials['type']) {
            'user' => User::class,
            'provider' => Provider::class,
            'store_manager' => Store::class,
            'driver' => Driver::class,
        };

        $account = $model::where('phone', $credentials['phone'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return [
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة'
            ];
        }

        $token = $account->createToken('mobile-token')->plainTextToken;

        return [
            'success' => true,
            'data' => [
                'type'  => $credentials['type'],
                'user'  => $account,
                'token' => $token
            ]
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
        return ['message' => 'Logged out'];
    }
}
