<?php
namespace App\Services\UserManagementServices;

use App\Models\UserManagement\Provider;
use App\Services\Service;
use Illuminate\Support\Facades\Hash;

class ProviderService extends Service {
    /**
     * Create a new provider.
     *
     * @param array $data
     * @return Provider
     */
    public function createProvider(array $data): Provider
    {
        $data['password'] = Hash::make($data['password']);

        return Provider::create($data);
    }
}
