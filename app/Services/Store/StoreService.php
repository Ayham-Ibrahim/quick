<?php

namespace App\Services\Store;

use App\Models\Store;
use App\Services\FileStorage;
use App\Services\Service;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreService extends Service
{
    public function paginate($perPage = 10)
    {
        return Store::with(['category', 'sub_category'])->paginate($perPage);
    }

    public function find($id)
    {
        $store = Store::with(['category', 'sub_category'])->find($id);

        if (!$store) {
            $this->throwExceptionJson('Store not found', 404);
        }

        return $store;
    }
    /**
     * Store a new provider store
     */
    public function storeStore($data)
    {
        try {
            return Store::create([
                'store_name'                 => $data['store_name'],
                'phone'                => $data['phone'],
                'store_owner_name'           => $data['store_owner_name'],
                'password'                   => bcrypt($data['password']),
                'commercial_register_image'  => FileStorage::storeFile($data['commercial_register_image'], 'Store', 'img'),
                'store_logo'                 => FileStorage::storeFile($data['store_logo'], 'Store', 'img'),
                'city'                       => $data['city'] ?? null,
                'v_location'                 => $data['v_location'],
                'h_location'                 => $data['h_location'],
                'category_id'                => $data['category_id'],
                'subcategory_id'             => $data['subcategory_id'],
            ]);
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }

    /**
     * Update an existing store
     */
    public function updateStore($data, Store $store)
    {
        try {
            $store->update(array_filter([
                'store_name'                 => $data['store_name'] ?? null,
                'phone'                => $data['phone'] ?? null,
                'store_owner_name'           => $data['store_owner_name'] ?? null,
                'password'                   => isset($data['password']) ? bcrypt($data['password']) : null,

                'commercial_register_image'  => FileStorage::fileExists(
                    $data['commercial_register_image'] ?? null,
                    $store->commercial_register_image,
                    'Store',
                    'img'
                ),

                'store_logo'                 => FileStorage::fileExists(
                    $data['store_logo'] ?? null,
                    $store->store_logo,
                    'Store',
                    'img'
                ),

                'city'                       => array_key_exists('city', $data) ? $data['city'] : null,
                'v_location'                 => $data['v_location'] ?? null,
                'h_location'                 => $data['h_location'] ?? null,
                'category_id'                => $data['category_id'] ?? null,
                'subcategory_id'             => $data['subcategory_id'] ?? null,
            ]));

            return $store;
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }

    /**
     * Delete store with images
     */
    public function deleteStore(Store $store)
    {
        try {
            FileStorage::deleteFile($store->commercial_register_image);
            FileStorage::deleteFile($store->store_logo);

            $store->delete();
            return true;
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }
}
