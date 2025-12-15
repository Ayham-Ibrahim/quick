<?php
namespace App\Services\Store;

use App\Models\Store;
use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\StoreResource;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreService extends Service
{
    public function paginate($perPage = 10)
    {
        $stores = Store::with(['subCategories', 'categories'])->paginate($perPage);

        return $stores;
    }

    public function listOfStores()
    {
        $stores = Store::select(['id','store_name', 'store_logo'])->get();

        return $stores;
    }

    public function find($id)
    {
        $store = Store::with(['subCategories', 'categories','ratings'])->find($id);

        if (! $store) {
            $this->throwExceptionJson('Store not found', 404);
        }

        return $store;
    }
    /**
     * Store a new store store
     */
    public function storeStore($data)
    {
        try {
            $store = Store::create([
                'store_name'                => $data['store_name'],
                'phone'                     => $data['phone'],
                'store_owner_name'          => $data['store_owner_name'],
                'password'                  => bcrypt($data['password']),
                'commercial_register_image' => FileStorage::storeFile($data['commercial_register_image'], 'Store', 'img'),
                'store_logo'                => FileStorage::storeFile($data['store_logo'], 'Store', 'img'),
                'city'                      => $data['city'] ?? null,
                'v_location'                => $data['v_location'],
                'h_location'                => $data['h_location'],
            ]);

            $store->categories()->sync($data['category_ids']);
            $store->subcategories()->sync($data['subcategory_ids']);
            return $store->load(['subCategories', 'categories']);
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
                'store_name'                => $data['store_name'] ?? null,
                'phone'                     => $data['phone'] ?? null,
                'store_owner_name'          => $data['store_owner_name'] ?? null,
                'password'                  => isset($data['password']) ? bcrypt($data['password']) : null,

                'commercial_register_image' => FileStorage::fileExists(
                    $data['commercial_register_image'] ?? null,
                    $store->commercial_register_image,
                    'Store',
                    'img'
                ),

                'store_logo'                => FileStorage::fileExists(
                    $data['store_logo'] ?? null,
                    $store->store_logo,
                    'Store',
                    'img'
                ),

                'city'                      => array_key_exists('city', $data) ? $data['city'] : null,
                'v_location'                => $data['v_location'] ?? null,
                'h_location'                => $data['h_location'] ?? null,
            ]));

            if (isset($data['category_ids'])) {
                $store->categories()->sync($data['category_ids']);
            }

            if (isset($data['subcategory_ids'])) {
                $store->subcategories()->sync($data['subcategory_ids']);
            }

            return $store->load(['subCategories', 'categories']);
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }
    /**
     * Update store data
     */
    public function updateStoreProfile(array $data)
    {
        try {
            $store = Auth::guard('store')->user();

            if (! $store instanceof Store) {
                throw new \Exception('غير مصرح لك بالقيام بهذا الإجراء.');
            }

            $store->update(array_filter([
                'store_name'                => $data['store_name'] ?? null,
                'phone'                     => $data['phone'] ?? null,
                'store_owner_name'          => $data['store_owner_name'] ?? null,
                'password'                  => isset($data['password']) ? bcrypt($data['password']) : null,

                'commercial_register_image' => FileStorage::fileExists(
                    $data['commercial_register_image'] ?? null,
                    $store->commercial_register_image,
                    'Store',
                    'img'
                ),

                'store_logo'                => FileStorage::fileExists(
                    $data['store_logo'] ?? null,
                    $store->store_logo,
                    'Store',
                    'img'
                ),

                'city'                      => array_key_exists('city', $data) ? $data['city'] : null,
                'v_location'                => $data['v_location'] ?? null,
                'h_location'                => $data['h_location'] ?? null,
            ]));

            if (isset($data['category_ids'])) {
                $store->categories()->sync($data['category_ids']);
            }

            if (isset($data['subcategory_ids'])) {
                $store->subcategories()->sync($data['subcategory_ids']);
            }

            return $store->load(['subCategories', 'categories']);
        } catch (\Throwable $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء تحديث بياناتك',
                500,
                $e->getMessage()
            );
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
