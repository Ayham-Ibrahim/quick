<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use App\Services\Service;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class CartService extends Service
{
    /**
     * Get or create active cart for current user
     */
    public function getOrCreateCart(): Cart
    {
        $user = Auth::user();

        $cart = Cart::where('user_id', $user->id)
            ->active()
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $user->id,
                'status' => Cart::STATUS_ACTIVE,
            ]);
        }

        return $cart->load([
            'items.product:id,name,current_price,quantity',
            'items.product.images',
            'items.variant.attributes.attribute',
            'items.variant.attributes.value',
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(array $data)
    {
        try {
            DB::beginTransaction();

            $cart = $this->getOrCreateCart();
            $product = Product::find($data['product_id']);

            if (!$product) {
                $this->throwExceptionJson('المنتج غير موجود', 404);
            }

            if (!$product->is_accepted) {
                $this->throwExceptionJson('هذا المنتج غير متاح حالياً', 400);
            }

            $variantId = $data['product_variant_id'] ?? null;
            $quantity = $data['quantity'] ?? 1;
            $price = $product->current_price;

            // If variant is specified, validate it
            if ($variantId) {
                $variant = ProductVariant::where('id', $variantId)
                    ->where('product_id', $product->id)
                    ->first();

                if (!$variant) {
                    $this->throwExceptionJson('المتغير غير موجود لهذا المنتج', 404);
                }

                if (!$variant->is_active) {
                    $this->throwExceptionJson('هذا المتغير غير متاح حالياً', 400);
                }

                if ($variant->stock_quantity < $quantity) {
                    $this->throwExceptionJson(
                        "الكمية المطلوبة غير متوفرة. المتوفر: {$variant->stock_quantity}",
                        400
                    );
                }

                $price = $variant->price;
            } else {
                // Check product stock if no variant
                if ($product->quantity !== null && $product->quantity < $quantity) {
                    $this->throwExceptionJson(
                        "الكمية المطلوبة غير متوفرة. المتوفر: {$product->quantity}",
                        400
                    );
                }
            }

            // Check if item already exists in cart
            $existingItem = $cart->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->first();

            if ($existingItem) {
                // Update quantity
                $newQuantity = $existingItem->quantity + $quantity;

                // Validate new quantity against stock
                if ($variantId) {
                    $variant = ProductVariant::find($variantId);
                    if ($variant->stock_quantity < $newQuantity) {
                        $this->throwExceptionJson(
                            "لا يمكن إضافة المزيد. الكمية الإجمالية تتجاوز المخزون المتاح ({$variant->stock_quantity})",
                            400
                        );
                    }
                }

                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $price, // Update to current price
                ]);
            } else {
                // Create new item
                $cart->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            DB::commit();

            return $this->getCartWithDetails($cart->id);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Cart addItem error: ' . $th->getMessage());

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(int $cartItemId, int $quantity)
    {
        try {
            DB::beginTransaction();

            $cart = $this->getOrCreateCart();
            $item = $cart->items()->find($cartItemId);

            if (!$item) {
                $this->throwExceptionJson('العنصر غير موجود في السلة', 404);
            }

            if ($quantity <= 0) {
                // Remove item if quantity is 0 or less
                $item->delete();
            } else {
                // Validate stock
                if (!$item->isQuantityAvailable($quantity)) {
                    $availableStock = $item->available_stock;
                    $this->throwExceptionJson(
                        "الكمية المطلوبة غير متوفرة. المتوفر: {$availableStock}",
                        400
                    );
                }

                $item->update([
                    'quantity' => $quantity,
                    'unit_price' => $item->current_price, // Sync price
                ]);
            }

            DB::commit();

            return $this->getCartWithDetails($cart->id);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Cart updateItemQuantity error: ' . $th->getMessage());

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem(int $cartItemId): Cart
    {
        $cart = $this->getOrCreateCart();
        $item = $cart->items()->find($cartItemId);

        if (!$item) {
            $this->throwExceptionJson('العنصر غير موجود في السلة', 404);
        }

        $item->delete();

        return $this->getCartWithDetails($cart->id);
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(): Cart
    {
        $cart = $this->getOrCreateCart();
        $cart->items()->delete();

        return $this->getCartWithDetails($cart->id);
    }

    /**
     * Validate cart before checkout
     * Returns array of issues if any
     */
    public function validateCart(): array
    {
        $cart = $this->getOrCreateCart();
        $issues = [];

        if ($cart->isEmpty()) {
            $issues[] = [
                'type' => 'empty_cart',
                'message' => 'السلة فارغة',
            ];
            return $issues;
        }

        foreach ($cart->items as $item) {
            // Check if product is still available
            if (!$item->product || !$item->product->is_accepted) {
                $issues[] = [
                    'type' => 'unavailable_product',
                    'item_id' => $item->id,
                    'product_name' => $item->product?->name ?? 'منتج محذوف',
                    'message' => 'هذا المنتج لم يعد متاحاً',
                ];
                continue;
            }

            // Check variant availability
            if ($item->product_variant_id && (!$item->variant || !$item->variant->is_active)) {
                $issues[] = [
                    'type' => 'unavailable_variant',
                    'item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'message' => 'هذا المتغير لم يعد متاحاً',
                ];
                continue;
            }

            // Check stock availability
            if (!$item->isQuantityAvailable()) {
                $issues[] = [
                    'type' => 'insufficient_stock',
                    'item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => $item->available_stock,
                    'message' => "الكمية المطلوبة ({$item->quantity}) غير متوفرة. المتوفر: {$item->available_stock}",
                ];
            }

            // Check price changes
            if ($item->hasPriceChanged()) {
                $issues[] = [
                    'type' => 'price_changed',
                    'item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'old_price' => $item->unit_price,
                    'new_price' => $item->current_price,
                    'message' => 'تغير سعر هذا المنتج منذ إضافته للسلة',
                ];
            }
        }

        return $issues;
    }

    /**
     * Sync all cart prices to current prices
     */
    public function syncPrices(): Cart
    {
        $cart = $this->getOrCreateCart();

        foreach ($cart->items as $item) {
            if ($item->hasPriceChanged()) {
                $item->syncPrice();
            }
        }

        return $this->getCartWithDetails($cart->id);
    }

    /**
     * Get cart with full details
     */
    public function getCartWithDetails(int $cartId): Cart
    {
        return Cart::with([
            'items.product:id,name,description,current_price,previous_price,quantity,store_id',
            'items.product.store:id,store_name,store_logo',
            'items.product.images',
            'items.variant:id,product_id,sku,price,stock_quantity,is_active',
            'items.variant.attributes.attribute:id,name',
            'items.variant.attributes.value:id,value',
        ])->findOrFail($cartId);
    }

    /**
     * Get cart summary (for header/quick view)
     */
    public function getCartSummary(): array
    {
        $cart = Cart::where('user_id', Auth::id())
            ->active()
            ->withCount('items')
            ->first();

        if (!$cart) {
            return [
                'items_count' => 0,
                'total_quantity' => 0,
                'subtotal' => 0,
            ];
        }

        $cart->load('items');

        return [
            'cart_id' => $cart->id,
            'items_count' => $cart->items->count(),
            'total_quantity' => $cart->total_items,
            'subtotal' => $cart->subtotal,
        ];
    }
}
