<?php

namespace Tests\Unit;

use App\Jobs\RepriceSyncedVariantsJob;
use App\Models\Categories\Category;
use App\Models\Categories\SubCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRatios;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepriceSyncedVariantsJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_synced_product_and_variant_prices_using_the_latest_exchange_rate(): void
    {
        ProfitRatios::create([
            'tag' => ProfitRatios::TAG_EXCHANGE_RATE,
            'ratio_name' => 'سعر صرف الدولار',
            'value' => 15800,
        ]);

        $product = $this->createProduct(true, 2);

        $syncedVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SYNC-1',
            'price' => 45000,
            'base_price_usd' => 3,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $manualProduct = $this->createProduct(false, 4);

        app(RepriceSyncedVariantsJob::class, ['exchangeRate' => 15800])->handle(app('App\Services\Pricing\DynamicPricingService'));

        $this->assertSame(31500.0, (float) $product->fresh()->current_price);
        $this->assertSame(47500.0, (float) $syncedVariant->fresh()->price);
        $this->assertSame(60000.0, (float) $manualProduct->fresh()->current_price);
    }

    private function createProduct(bool $syncEnabled, float $basePriceUsd): Product
    {
        $category = Category::create([
            'name' => 'Category',
            'image' => 'category.png',
        ]);

        $subCategory = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Sub Category',
            'image' => 'subcategory.png',
            'price_depends_on_attributes' => true,
            'quantity_depends_on_attributes' => true,
        ]);

        $store = Store::create([
            'store_name' => 'Demo Store',
            'phone' => '+963900000002',
            'store_owner_name' => 'Owner',
            'password' => 'secret123',
            'commercial_register_image' => 'cr.png',
            'store_logo' => 'logo.png',
            'v_location' => '1',
            'h_location' => '1',
        ]);

        return Product::create([
            'store_id' => $store->id,
            'name' => 'Variant Product',
            'description' => 'desc',
            'quantity' => null,
            'current_price' => $basePriceUsd * 15000,
            'base_price_usd' => $syncEnabled ? $basePriceUsd : null,
            'sync_enabled' => $syncEnabled,
            'previous_price' => null,
            'sub_category_id' => $subCategory->id,
            'is_accepted' => true,
        ]);
    }
}