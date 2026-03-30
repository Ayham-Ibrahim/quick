<?php
namespace App\Services\Store;

use App\Models\Store;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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

    public function getStoresByCategory($categoryId)
    {
        $stores = Store::whereHas('categories', function ($query) use ($categoryId) {
            $query->where('categories.id', $categoryId);
        })->with(['subCategories', 'categories'])->get();

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
            return DB::transaction(function () use ($data, $store) {
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
                    // جلب الأقسام الفرعية الحالية قبل التحديث
                    $currentSubcategoryIds = $store->subcategories()->pluck('sub_categories.id')->toArray();
                    $newSubcategoryIds = $data['subcategory_ids'];

                    // تحديد الأقسام الفرعية المحذوفة
                    $removedSubcategoryIds = array_diff($currentSubcategoryIds, $newSubcategoryIds);

                    // التحقق من عدم وجود طلبات نشطة قبل السماح بحذف الفئة
                    if (!empty($removedSubcategoryIds)) {
                        $this->validateNoActiveOrdersForSubcategories($store->id, $removedSubcategoryIds);
                    }

                    // تحديث الأقسام الفرعية
                    $store->subcategories()->sync($newSubcategoryIds);

                    // حذف المنتجات التابعة للأقسام التي أزيلت
                    if (!empty($removedSubcategoryIds)) {
                        $this->handleRemovedSubcategoryProducts($store->id, $removedSubcategoryIds);
                    }
                }

                return $store->load(['subCategories', 'categories']);
            });
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

            return DB::transaction(function () use ($data, $store) {
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
                    // جلب الأقسام الفرعية الحالية قبل التحديث
                    $currentSubcategoryIds = $store->subcategories()->pluck('sub_categories.id')->toArray();
                    $newSubcategoryIds = $data['subcategory_ids'];

                    // تحديد الأقسام الفرعية المحذوفة
                    $removedSubcategoryIds = array_diff($currentSubcategoryIds, $newSubcategoryIds);

                    // التحقق من عدم وجود طلبات نشطة قبل السماح بحذف الفئة
                    if (!empty($removedSubcategoryIds)) {
                        $this->validateNoActiveOrdersForSubcategories($store->id, $removedSubcategoryIds);
                    }

                    // تحديث الأقسام الفرعية
                    $store->subcategories()->sync($newSubcategoryIds);

                    // حذف المنتجات التابعة للأقسام التي أزيلت
                    if (!empty($removedSubcategoryIds)) {
                        $this->handleRemovedSubcategoryProducts($store->id, $removedSubcategoryIds);
                    }
                }

                return $store->load(['subCategories', 'categories']);
            });
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

    /**
     * التحقق من عدم وجود طلبات نشطة للمنتجات في الأقسام المحذوفة
     * 
     * - إذا كان هناك طلبات نشطة (pending أو shipping) تحتوي على منتجات من هذه الأقسام
     *   لا يُسمح بإزالة المتجر من هذه الفئة
     */
    private function validateNoActiveOrdersForSubcategories(int $storeId, array $removedSubcategoryIds): void
    {
        // جلب المنتجات المتأثرة في الأقسام المحذوفة
        $affectedProductIds = Product::where('store_id', $storeId)
            ->whereIn('sub_category_id', $removedSubcategoryIds)
            ->pluck('id')
            ->toArray();

        if (empty($affectedProductIds)) {
            return;
        }

        // التحقق من وجود طلبات نشطة (pending أو shipping) تحتوي على هذه المنتجات
        $activeOrdersCount = OrderItem::whereIn('product_id', $affectedProductIds)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_SHIPPING]);
            })
            ->count();

        if ($activeOrdersCount > 0) {
            $this->throwExceptionJson(
                'لا يمكن إزالة هذه الفئة لأن هناك طلبات نشطة تحتوي على منتجات منها',
                400
            );
        }
    }

    /**
     * حذف المنتجات وعناصر السلة التابعة للأقسام المحذوفة بعد التأكد من عدم وجود طلبات نشطة
     */
    private function handleRemovedSubcategoryProducts(int $storeId, array $removedSubcategoryIds): void
    {
        $affectedProducts = Product::where('store_id', $storeId)
            ->whereIn('sub_category_id', $removedSubcategoryIds)
            ->get();

        if ($affectedProducts->isEmpty()) {
            return;
        }

        $productIds = $affectedProducts->pluck('id')->toArray();

        // حذف عناصر السلة الحالية المرتبطة بهذه المنتجات
        CartItem::whereIn('product_id', $productIds)->delete();

        // حذف المنتجات
        Product::whereIn('id', $productIds)->delete();

        Log::info('Products removed for store when subcategory removed', [
            'store_id' => $storeId,
            'removed_subcategory_ids' => $removedSubcategoryIds,
            'product_ids' => $productIds,
        ]);
    }
}
