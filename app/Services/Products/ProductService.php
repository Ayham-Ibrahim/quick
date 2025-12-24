<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService extends Service
{
    /**
     * add new product to the database
     * @param mixed $data
     * @return Product|null
     * @throws \Exception If an error occurs during the transaction.
     */
    public function storeProduct($data)
    {
        try {
            DB::beginTransaction();
            $product = Product::create([
                'store_id' => $data['store_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'quantity' => $data['quantity'] ?? null,
                'current_price' => $data['current_price'] ?? null,
                'previous_price' => $data['previous_price'] ?? null,
                'sub_category_id' => $data['sub_category_id'],
                'is_accepted' => false, // Assuming new products need admin approval
            ]);

            $this->storeProductImages($product, $data['images']);

            // $this->storeProductVariants($product, $data['variants']);
            DB::commit();

            return $product->load('images');
        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollBack();
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     * Updates an existing product along with its images.
     * @param mixed $data
     * @param \App\Models\Product $product
     * @return Product|null
     * @throws \Exception If an error occurs during the transaction.
     */
    public function updateProduct($data, Product $product)
    {
        try {
            DB::beginTransaction();

            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'quantity' => $data['quantity'] ?? $product->quantity,
                'current_price' => $data['current_price'] ?? $product->current_price,
                'previous_price' => $data['previous_price'] ?? $product->previous_price,
                'sub_category_id' => $data['sub_category_id'] ?? $product->sub_category_id,
            ]);

            if (isset($data['images'])) {
                $product->images()->delete();
                $this->storeProductImages($product, $data['images']);
            }

            // if (isset($data['variants'])) {
            //     $this->updateProductVariants($product, $data['variants']);
            // }

            DB::commit();
            return $product->load('images');
        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollBack();
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     * Stores images for the specified product.
     *
     * @param \App\Models\Product $product The product instance.
     * @param array $images The array of image data, each containing a file.
     */
    protected function storeProductImages(Product $product, array $images)
    {
        $productImages = collect($images)->map(function ($imageItem) {

            // Expecting: [ "file" => UploadedFile ]
            $file = $imageItem['file'] ?? null;

            if ($file instanceof \Illuminate\Http\UploadedFile) {
                return [
                    'image' => FileStorage::storeFile($file, 'Product', 'img'),
                ];
            }

            Log::error('Invalid image file type:', ['image' => $imageItem]);
            return null;
        })->filter()->toArray();

        if (!empty($productImages)) {
            $product->images()->createMany($productImages);
        }
    }
}
